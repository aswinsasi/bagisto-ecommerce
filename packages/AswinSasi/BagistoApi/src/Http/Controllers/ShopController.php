<?php

namespace AswinSasi\BagistoApi\Http\Controllers;

use Webkul\Category\Repositories\CategoryRepository;
use Webkul\Product\Repositories\ProductRepository;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Exception;
use Webkul\Checkout\Facades\Cart;
use Illuminate\Support\Facades\DB;
use Webkul\Sales\Repositories\OrderRepository;
use Illuminate\Support\Facades\Session;
use Webkul\Sales\Transformers\OrderResource as OrderTransformer;

class ShopController extends Controller
{
    protected $categoryRepository;
    protected $productRepository;

    public function __construct(
        CategoryRepository $categoryRepository,
        ProductRepository $productRepository
    ) {
        $this->categoryRepository = $categoryRepository;
        $this->productRepository = $productRepository;
    }

    public function getCategories()
    { 
        try {
            $categories = $this->categoryRepository->all();

            return response()->json([
                'success' => true,
                'data'    => $categories,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to fetch categories', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch categories.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function getProducts(Request $request)
    {
        try {
            $query = $this->productRepository->with(['categories.translations', 'attribute_values.attribute']);

            // Join with product_price_indices to use price filter
            $query->leftJoin('product_price_indices as ppi', 'products.id', '=', 'ppi.product_id')
                ->select('products.*', 'ppi.min_price'); // ensure min_price is included

            // Filter by Category ID
            if ($request->has('category_id')) {
                $query->whereHas('categories', function ($q) use ($request) {
                    $q->where('id', $request->category_id);
                });
            }

            // Filter by Price Range
            if ($request->has('price_min') && $request->has('price_max')) {
                $query->whereBetween('ppi.min_price', [
                    floatval($request->price_min),
                    floatval($request->price_max),
                ]);
            }

            // Filter by Color
            if ($request->has('color')) {
                $color = $request->color;

                $query->whereHas('attribute_values', function ($q) use ($color) {
                    $q->whereHas('attribute', function ($attrQuery) {
                        $attrQuery->where('code', 'color');
                    });

                    $q->whereIn('integer_value', function ($subQuery) use ($color) {
                        $subQuery->select('id')
                            ->from('attribute_options')
                            ->where('admin_name', $color);
                    });
                });
            }

            // Filter by Size
            if ($request->has('size')) {
                $size = $request->size;

                $query->whereHas('attribute_values', function ($q) use ($size) {
                    $q->whereHas('attribute', function ($attrQuery) {
                        $attrQuery->where('code', 'size');
                    });

                    $q->whereIn('integer_value', function ($subQuery) use ($size) {
                        $subQuery->select('id')
                            ->from('attribute_options')
                            ->where('admin_name', $size);
                    });
                });
            }

            $products = $query->get();

            $result = $products->map(function ($product) {
                $category = $product->categories->first();

                return [
                    'id'       => $product->id,
                    'name'     => $product->name,
                    'price'    => $product->price,
                    'category' => $category ? ($category->name ?? 'Unnamed') : 'No category',
                ];
            });

            return response()->json([
                'success' => true,
                'data'    => $result,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to fetch filtered products', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch products.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function addToCart(Request $request)
    {
        try {
            // Step 1: Validate request
            $validated = $request->validate([
                'product_id' => 'required|integer|exists:products,id',
                'quantity'   => 'required|integer|min:1',
            ]);

            // Step 2: Fetch the product
            $product = $this->productRepository->findOrFail($validated['product_id']);

            // Step 3: Prepare parameters
            $params = [
                'product_id' => $product->id,
                'quantity'   => $validated['quantity'],
            ];

            // Step 4: Add product to the cart
             $result = Cart::addProduct($product, $params);

            // Step 5: Handle failure
            if (isset($result['error']) && $result['error']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'] ?? 'Could not add product to cart.',
                ], 422);
            }

            // Step 6: Return cart data
            return response()->json([
                'success' => true,
                'message' => 'Product added to cart successfully.',
                'cart'    => Cart::getCart(),          // Full cart summary
                'items'   => Cart::getCart()->items,     // Items in cart
            ]);

        } catch (\Illuminate\Validation\ValidationException $ve) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $ve->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Add to cart failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }



 public function checkout(Request $request)
    {
        try {
            // Step 1: Start Bagisto session
            if ($request->hasCookie('bagisto_session')) {
                Session::setId($request->cookie('bagisto_session'));
                Session::start();
            }

            // Step 2: Validate input
            $validated = $request->validate([
                'billing'         => 'required|array',
                'shipping'        => 'required|array',
                'customer_email'  => 'required|email',
                'payment_method'  => 'required|string',
                'shipping_method' => 'required|string',
            ]);

            // Step 3: Get Cart
            $cart = Cart::getCart();
            if (!$cart || $cart->items->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cart is empty.',
                ], 400);
            }

            DB::beginTransaction();

            // Step 4: Set customer info
            $cart->customer_email = $validated['customer_email'];
            $cart->is_guest = 1;
            $cart->save();

            // Step 5: Save addresses
            Cart::saveAddresses([
                'billing'  => $validated['billing'],
                'shipping' => $validated['shipping'],
            ]);

            // Step 6: Save shipping method
            $shippingSaved = Cart::saveShippingMethod($validated['shipping_method']);
            if (!$shippingSaved) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid shipping method.',
                    'available_methods' => optional($cart->shipping_rates)->pluck('method'),
                ], 422);
            }

            // Step 7: Save payment method
            Cart::savePaymentMethod(['method' => $validated['payment_method']]);

            // Step 8: Collect totals
            Cart::collectTotals();

            // Step 9: Final cart check
            $cart = Cart::getCart();

            if (!$cart->payment || !$cart->payment->method) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'No payment method selected.',
                ], 422);
            }

            if (!$cart->selected_shipping_rate) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'No shipping method selected.',
                ], 422);
            }

            $shippingAddress = $validated['shipping'];
            if (isset($shippingAddress['address']) && is_array($shippingAddress['address'])) {
                $shippingAddress['address'] = implode(', ', $shippingAddress['address']);
            }

            $billingAddress = $validated['billing'];
            if (isset($billingAddress['address']) && is_array($billingAddress['address'])) {
                $billingAddress['address'] = implode(', ', $billingAddress['address']);
            }

            $orderData = [
                'customer_email'   => $cart->customer_email,
                'customer_id'      => $cart->customer_id ?? null,
                'is_guest'         => $cart->is_guest,
                'shipping_address' => $shippingAddress,
                'billing_address'  => $billingAddress,
                'shipping_method'  => $validated['shipping_method'],
                'payment'          => ['method' => $validated['payment_method']],
                'items'            => [],
                'grand_total'      => $cart->base_grand_total,
                'sub_total'        => $cart->base_sub_total,
                'tax_amount'       => $cart->base_tax_total ?? 0,
                'shipping_amount'  => $cart->base_shipping_amount ?? 0,
            ];

          foreach ($cart->items as $item) {
            $childrenData = [];

            if ($item->children && $item->children->count() > 0) {
                foreach ($item->children as $child) {
                    $childrenData[] = [
                        'product_id' => $child->product_id,
                        'sku'        => $child->sku,
                        'name'       => $child->name,
                        'price'      => $child->price,
                        'quantity'   => $child->quantity,
                        'total'      => $child->total,
                        'children'   => [], // Usually children of children rarely exist, keep empty
                    ];
                }
            }

            $orderData['items'][] = [
                'product_id' => $item->product_id,
                'sku'        => $item->sku,
                'name'       => $item->name,
                'price'      => $item->price,
                'quantity'   => $item->quantity,
                'total'      => $item->total,
                'children'   => $childrenData,
            ];
        }

            // Step 10: Place order
            $orderRepository = app(OrderRepository::class);

            $order = $orderRepository->create((new OrderTransformer($cart))->jsonSerialize());

            // Step 11: Deactivate cart
            Cart::deActivateCart();

            DB::commit();

            return response()->json([
                'success'  => true,
                'message'  => 'Order placed successfully.',
                'order_id' => $order->id,
                'order'    => $order,
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $ve) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $ve->errors(),
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Checkout Failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Checkout failed. Try again.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }


//     try {
//         // Step 0: Set session ID from 'bagisto_session' cookie (if present)
//         if ($request->hasCookie('bagisto_session')) {
//             Session::setId($request->cookie('bagisto_session'));
//             Session::start();
//         }

//         // Step 1: Validate input
//         $validated = $request->validate([
//             'billing'          => 'required|array',
//             'shipping'         => 'required|array',
//             'customer_email'   => 'required|email',
//             'payment_method'   => 'required|string',
//             'shipping_method'  => 'required|string',
//         ]);

//         // Step 2: Get cart instance by session or current user
//         $cart = Cart::getCart();

//         if (!$cart || $cart->items->isEmpty()) {
//             return response()->json([
//                 'success' => false,
//                 'message' => 'Cart is empty.',
//             ], 422);
//         }

//         DB::beginTransaction();

//         // Step 3: Update cart details (use cart instance)
//         $cart->customer_email = $validated['customer_email'];
//         $cart->is_guest = 1; // Assuming guest checkout, else set customer_id
//         $cart->save();

//         // Save addresses
//         Cart::saveAddresses([
//             'billing'  => $validated['billing'],
//             'shipping' => $validated['shipping'],
//         ]);

//         // Save shipping method
//      $success = Cart::saveShippingMethod($validated['shipping_method']);

//     if (!$success) {
//         Log::error('Invalid shipping method provided.', [
//             'provided' => $validated['shipping_method'],
//             'available' => $cart->shipping_rates->pluck('method')->toArray()
//         ]);

//         DB::rollBack();
//         return response()->json([
//             'success' => false,
//             'message' => 'Invalid shipping method.',
//             'provided' => $validated['shipping_method'],
//             'available_methods' => $cart->shipping_rates->pluck('method'),
//         ], 422);
//     }

//         // Save payment method
//        Cart::savePaymentMethod(['method' => $validated['payment_method']]);
//         Cart::collectTotals();

//         // Refresh the cart to access updated relations
//         $cart = Cart::getCart();

//         if (!$cart->payment || !$cart->payment->method) {
//             return response()->json([
//                 'success' => false,
//                 'message' => 'No payment method selected.',
//             ], 422);
//         }

//         // Collect totals after all updates
//         Cart::collectTotals();

//         // Double-check that selected shipping rate and payment exist before creating order
//         if (!$cart->selected_shipping_rate) {
//             DB::rollBack();
//             return response()->json([
//                 'success' => false,
//                 'message' => 'No shipping method selected.',
//             ], 422);
//         }

//         if (!$cart->payment) {
//             DB::rollBack();
//             return response()->json([
//                 'success' => false,
//                 'message' => 'No payment method selected.',
//             ], 422);
//         }

//         // Step 4: Create order using order repository with expected array data
//         $orderRepository = app(OrderRepository::class);
//         $order = $orderRepository->create([
//             'cart_id'               => $cart->id,
//             'customer_id'           => $cart->customer_id ?? null,
//             'customer_email'        => $cart->customer_email,
//             'is_guest'              => $cart->is_guest,
//             'channel'               => core()->getCurrentChannel()->code,
//             'channel_name'          => core()->getCurrentChannel()->name,
//             'shipping_address' => $cart->shipping_address->toArray(),
//             'billing_address'  => $cart->billing_address->toArray(),
//             'shipping_method'       => $cart->selected_shipping_rate->method,
//             'shipping_method_title' => $cart->selected_shipping_rate->method_title,
//             'payment'               => [
//                 'method' => $cart->payment->method,
//             ],
//         ]);

//         // Deactivate cart after successful order creation
//         Cart::deActivateCart();

//         DB::commit();

//         return response()->json([
//             'success'  => true,
//             'message'  => 'Checkout completed successfully.',
//             'order_id' => $order->id,
//             'order'    => $order,
//         ]);
//     } catch (\Illuminate\Validation\ValidationException $ve) {
//         return response()->json([
//             'success' => false,
//             'message' => 'Validation failed.',
//             'errors'  => $ve->errors(),
//         ], 422);
//     } catch (\Exception $e) {
//         DB::rollBack();
//         Log::error('Checkout error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

//         return response()->json([
//             'success' => false,
//             'message' => 'Checkout failed due to an internal error.',
//             'error'   => $e->getMessage(),
//         ], 500);
//     }
// }

}
