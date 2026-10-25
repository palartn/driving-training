<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * حارس أمان: الاختبارات تمسح قاعدة البيانات (RefreshDatabase)،
     * فلا تعمل إلا على اتصال mysql باسم قاعدة ينتهي بـ _test، حتى لا تُمسح بيانات التطوير أبدًا.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        $default = (string) $app['config']->get('database.default');
        $database = (string) $app['config']->get("database.connections.{$default}.database");

        if ($default !== 'mysql' || ! str_ends_with($database, '_test')) {
            throw new RuntimeException(
                "Refusing to run tests: they require the mysql connection and a database name ending in _test (got connection [{$default}], database [{$database}])."
            );
        }

        return $app;
    }
}
