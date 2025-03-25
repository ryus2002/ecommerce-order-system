<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * 设置测试环境
     */
    protected function setUp(): void
    {
        parent::setUp();

        // 可以在此处添加测试环境特定的设置

        // 禁用SQLite外键约束检查
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');
        }
    }

    /**
     * 清理测试环境
     */
    protected function tearDown(): void
    {
        // 如果需要，可以在这里添加清理代码

        parent::tearDown();
    }
}