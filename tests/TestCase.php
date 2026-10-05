<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Nexor\Cms\Support\RootHtaccess;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Установка CMS ставит корневой .htaccess — в тестах он ложится во
        // временную папку, а не в настоящий корень проекта.
        RootHtaccess::usePath(storage_path('framework/testing/root.htaccess'));
        @unlink(RootHtaccess::path());
        @unlink(RootHtaccess::path().'.bak');
    }

    protected function tearDown(): void
    {
        @unlink(RootHtaccess::path());
        @unlink(RootHtaccess::path().'.bak');
        RootHtaccess::usePath(null);

        parent::tearDown();
    }
}
