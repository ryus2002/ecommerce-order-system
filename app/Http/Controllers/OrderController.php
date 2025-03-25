<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
    protected $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    /**
     * 显示订单列表
     */
    public function index()
    {
        $orders = Order::where('user_id', Auth::id())->with('items')->get();
        return response()->json($orders);
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'items' => 'required|array',
                'items.*.product_id' => 'required|exists:products,id',
                'items.*.quantity' => 'required|integer|min:1',
                'items.*.unit_price' => 'required|numeric|min:0',
                'total_amount' => 'required|numeric|min:0',
                'address' => 'nullable|string',
                'payment_method' => 'nullable|string',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // 明确返回 JSON 错误
            return response()->json([
                'message' => '验证失败',
                'errors' => $e->errors(),
            ], 422);
        }
    
        // 使用订单服务创建订单
        try {
            $order = $this->orderService->createOrder(
                auth()->id(),
                $validated['items'],
                $validated['total_amount']
            );
    
            return response()->json([
                'message' => '订单创建成功',
                'order' => $order->load('items'),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => '订单创建失败',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 显示特定订单
     */
    public function show(Order $order)
    {
        // 确保用户只能查看自己的订单
        if ($order->user_id !== Auth::id()) {
            return response()->json(['message' => '无权访问此订单'], 403);
        }

        return response()->json($order->load('items'));
    }
}