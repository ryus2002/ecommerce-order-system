<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class DistributedLockService
{
    private string $lockPrefix = 'lock:';
    private int $defaultTtl = 30; // 鎖的預設生存時間（秒）
    
    public function acquire(string $resource, int $ttl = null): ?string
    {
        $token = Str::uuid()->toString();
        $ttl = $ttl ?: $this->defaultTtl;
        $key = $this->lockPrefix . $resource;
        
        // 使用SET NX EX命令嘗試獲取鎖
        // NX：表示「只有當鍵不存在時才設定值」，確保只有第一個請求能成功上鎖。
        // EX：設定鍵的過期時間，單位為秒，避免鎖永遠存在。
        $acquired = Redis::set($key, $token, 'EX', $ttl, 'NX');
        
        return $acquired ? $token : null;
    }
    
    public function release(string $resource, string $token): bool
    {
        $key = $this->lockPrefix . $resource;
        
        // Lua腳本確保只有鎖的擁有者才能釋放鎖
        $script = "
            if redis.call('get', KEYS[1]) == ARGV[1] then
                return redis.call('del', KEYS[1])
            else
                return 0
            end
        ";
        
        return (bool) Redis::eval($script, 1, $key, $token);
    }
}
