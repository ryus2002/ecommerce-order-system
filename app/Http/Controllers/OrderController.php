    public function store(Request $request)
    {
    $validated = $request->validate([
        'items' => 'required|array',
        'items.*.product_id' => 'required|exists:products,id',
        'items.*.quantity' => 'required|integer|min:1',
        'items.*.unit_price' => 'required|numeric|min:0',
        'total_amount' => 'required|numeric|min:0',
        'address' => 'required|string',
        'payment_method' => 'required|string',
    ]);

    // 使用 DB 事務確保數據一致性
    DB::beginTransaction();
    try {
        // 創建訂單
        $order = new Order();
        $order->user_id = auth()->id();
        $order->address = $validated['address'];
        $order->payment_method = $validated['payment_method'];
        $order->status = 'pending';
        $order->save();

        // 創建訂單項目
        foreach ($validated['items'] as $item) {
            $orderItem = new OrderItem();
            $orderItem->order_id = $order->id;
            $orderItem->product_id = $item['product_id'];
            $orderItem->quantity = $item['quantity'];
            $orderItem->unit_price = $item['unit_price'];
            $orderItem->save();
    }

        DB::commit();
        
        return response()->json([
            'message' => '訂單創建成功',
            'order' => $order->load('items'),
        ], 201);
    } catch (\Exception $e) {
        DB::rollBack();
        
        return response()->json([
            'message' => '訂單創建失敗',
            'error' => $e->getMessage(),
        ], 500);
        }
    }
