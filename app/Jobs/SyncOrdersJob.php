<?php

namespace App\Jobs;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class SyncOrdersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var string YYYY-MM-DD in Asia/Dhaka */
    public string $date;
    /** @var string dev|prod */
    public string $env;

    public $tries = 1;
    public $backoff = [5, 15, 60];

    public function __construct(string $date, string $env = 'dev')
    {
        $this->date = $date;
        $this->env = $env;
    }

    public function handle(): void
    {
        // Resolve endpoint/token from env
        $url = $this->env === 'prod' ? env('ORDER_API_PROD_URL') : env('ORDER_API_DEV_URL');
        $token = $this->env === 'prod' ? env('ORDER_API_PROD_TOKEN') : env('ORDER_API_DEV_TOKEN');

        // For a one-day sync, use the BD calendar date as-is (API expects YYYY-MM-DD)
        $bdDate = \Carbon\Carbon::parse($this->date, 'Asia/Dhaka')->toDateString();

        // GraphQL now uses orderDate { start, end } instead of createdAt_* filters
        $query = <<<'GQL'
    query OrdersByDate($filter: OrderFilterInput!, $pagination: PaginationInput) {
      getOrders(filter: $filter, pagination: $pagination) {
        result {
          count
          orders {
            uid
            status
            payment {
              gateway
              paymentGatewayCode
              status
              transactionId
              emi {
                bankName
                cardType
                month
              }
            }
            createdAt
            updatedAt
            products {
              enName
              model
              variant {
                colorFamily
                quantity
                mrpPrice
                size
                posItemCode
              }
              seller {
                uid
                enName
              }
              discount {
                amount
                type
                calculatedDiscount
              }
            }
            shippingMethod
            price {
              total
              rewardPointDiscount {
                discountAmount
                redeemPoint
              }
              deliveryDiscountAmount
              earnRewardPoint
              discountedAmount
              customerPayable
              subTotal
              vat
            }
            customer {
              contact {
                email
              }
            }
            receiver {
              addressLabel
              address
              area {
                enName
              }
              name
              phoneNumber
              zone {
                enName
              }
            }
            billingAddress {
              address
              addressLabel
              area {
                enName
              }
              name
              phoneNumber
              zone {
                enName
              }
            }
            promocodeDetails {
              code
              discount
              discountType
            }
            shippingCharges {
              deliveryDiscountAmount
              payableShippingCharge
            }
          }
        }
      }
    }
    GQL;

        $limit = (int)(config('oms.poll.page_size') ?? 100);

        $skip = 0;
        $fetched = 0;
        $total = null;

        do {
            $response = \Illuminate\Support\Facades\Http::withToken($token)
                ->timeout(10)
                ->retry(3, 500)
                ->post($url, [
                    'query' => $query,
                    'variables' => [
                        'filter' => [
                            'orderDate' => [
                                'start' => $bdDate,   // e.g. "2025-09-09"
                                'end' => $bdDate,   // same day for daily sync
                            ],
                        ],
                        'pagination' => [
                            'limit' => $limit,
                            'skip' => $skip,
                        ],
                    ],
                ])
                ->throw();

            $json = $response->json();

            // If server returns GraphQL errors, log them clearly
            if (!empty($json['errors'])) {
                \Log::error('getOrders GraphQL errors', ['errors' => $json['errors']]);
                throw new \RuntimeException('GraphQL getOrders returned errors: ' . json_encode($json['errors']));
            }

            $orders = data_get($json, 'data.getOrders.result.orders', []);
            $total = $total ?? data_get($json, 'data.getOrders.result.count');

            foreach ($orders as $node) {
                $order = \App\Models\Order::updateOrCreate(
                    ['uid' => $node['uid'], 'environment' => $this->env],
                    [
                        'status' => $node['status'] ?? null,
                        'payment_method' => data_get($node, 'payment.type'),
                        'payment_status' => data_get($node, 'payment.status'),
                        'created_at_external' => data_get($node, 'createdAt'),
                        'updated_at_external' => data_get($node, 'updatedAt'),
                        'currency' => 'BDT', // map if your API provides currency
                        'total_amount' => data_get($node, 'price.total'),
                        'customer_email' => data_get($node, 'customer.contact.email'),
                        'raw' => $node,
                    ]
                );
            }

            $countThisPage = count($orders);
            $fetched += $countThisPage;

            $skip += $limit;

            // Stop conditions:
            // - If API returns total count, stop when fetched >= total
            // - If no total, stop when this page returned fewer than limit
            if ($total !== null) {
                if ($fetched >= (int)$total) break;
            } else {
                if ($countThisPage < $limit) break;
            }

        } while (true);
    }
}
