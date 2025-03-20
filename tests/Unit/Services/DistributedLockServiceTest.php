<?php

namespace Tests\Unit\Services;

use App\Services\DistributedLockService;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class DistributedLockServiceTest extends TestCase
{
    private DistributedLockService $lockService;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->lockService = new DistributedLockService();
    }
    
    public function testAcquireLock()
    {
        // 模擬Redis::set返回true
        Redis::shouldReceive('set')
            ->once()
            ->andReturn(true);
        
        $token = $this->lockService->acquire('test-resource');
        
        $this->assertNotNull($token);
        $this->assertIsString($token);
    }
    
    public function testAcquireLockWhenAlreadyLocked()
    {
        // 模擬Redis::set返回false (鎖已被其他進程獲取)
        Redis::shouldReceive('set')
            ->once()
            ->andReturn(false);
        
        $token = $this->lockService->acquire('test-resource');
        
        $this->assertNull($token);
    }
    
    public function testReleaseLock()
    {
        // 模擬Redis::eval返回1 (成功釋放鎖)
        Redis::shouldReceive('eval')
            ->once()
            ->andReturn(1);
        
        $result = $this->lockService->release('test-resource', 'test-token');
        
        $this->assertTrue($result);
    }
    
    public function testReleaseLockWhenTokenMismatch()
    {
        // 模擬Redis::eval返回0 (token不匹配)
        Redis::shouldReceive('eval')
            ->once()
            ->andReturn(0);
        
        $result = $this->lockService->release('test-resource', 'wrong-token');
        
        $this->assertFalse($result);
    }
}
