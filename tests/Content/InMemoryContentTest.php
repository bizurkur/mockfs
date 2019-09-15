<?php declare(strict_types = 1);

namespace MockFileSystem\Tests\Content;

use MockFileSystem\Content\AbstractContent;
use MockFileSystem\Content\InMemoryContent;
use MockFileSystem\Tests\Content\ContentTestCase;

class InMemoryContentTest extends ContentTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->fixture = new InMemoryContent();
    }

    public function testInstanceOf(): void
    {
        self::assertInstanceOf(AbstractContent::class, $this->fixture);
    }
}
