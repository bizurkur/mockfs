<?php

declare(strict_types=1);

namespace MockFileSystem\Tests;

use MockFileSystem\Components\DirectoryInterface;
use MockFileSystem\MockFileSystem;
use MockFileSystem\Visitor\TreeVisitor;
use PHPUnit\Framework\TestCase;

class VisitorTest extends TestCase
{
    /**
     * @var DirectoryInterface
     */
    private DirectoryInterface $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = MockFileSystem::create();
    }

    /**
     * @dataProvider sampleOnePartition
     */
    public function testOnePartitionVisitWholeFileSystem(
        array $structure,
        ?string $path,
        array $options,
        string $expected
    ): void {
        $handle = fopen('php://memory', 'w+');
        if ($handle === false) {
            self::fail('Failed to open file handle');
        }
        $visitor = new TreeVisitor($handle, $options);

        MockFileSystem::addStructure($structure, $this->root);

        $file = null;
        if ($path !== null) {
            $file = MockFileSystem::find($path);
        }
        MockFileSystem::visit($file, $visitor);

        $actual = stream_get_contents($handle, -1, 0);

        self::assertEquals($expected, $actual);
    }

    public function sampleOnePartition(): array
    {
        $structure = [
            'dev' => [
                '[null]' => null,
            ],
            'usr' => [
                'local' => [
                    'bin' => [
                        'php' => '',
                        'python' => '',
                        'composer' => '',
                    ],
                ],
            ],
            'etc' => [
                'ssh' => [
                    'ssh_config' => '',
                ],
                'passwd' => '',
                'shadow' => '',
            ],
        ];

        return [
            'visit whole file system' => [
                'structure' => $structure,
                'path' => null,
                'options' => [],
                'expected' => 'mfs://' . PHP_EOL
                    . '└── /' . PHP_EOL
                    . '    ├── dev' . PHP_EOL
                    . '    │   └── null' . PHP_EOL
                    . '    ├── etc' . PHP_EOL
                    . '    │   ├── passwd' . PHP_EOL
                    . '    │   ├── shadow' . PHP_EOL
                    . '    │   └── ssh' . PHP_EOL
                    . '    │       └── ssh_config' . PHP_EOL
                    . '    └── usr' . PHP_EOL
                    . '        └── local' . PHP_EOL
                    . '            └── bin' . PHP_EOL
                    . '                ├── composer' . PHP_EOL
                    . '                ├── php' . PHP_EOL
                    . '                └── python' . PHP_EOL
                    . PHP_EOL . '6 directories, 7 files' . PHP_EOL,
            ],
            'visit root' => [
                'structure' => $structure,
                'path' => '/',
                'options' => [],
                'expected' => '/' . PHP_EOL
                    . '├── dev' . PHP_EOL
                    . '│   └── null' . PHP_EOL
                    . '├── etc' . PHP_EOL
                    . '│   ├── passwd' . PHP_EOL
                    . '│   ├── shadow' . PHP_EOL
                    . '│   └── ssh' . PHP_EOL
                    . '│       └── ssh_config' . PHP_EOL
                    . '└── usr' . PHP_EOL
                    . '    └── local' . PHP_EOL
                    . '        └── bin' . PHP_EOL
                    . '            ├── composer' . PHP_EOL
                    . '            ├── php' . PHP_EOL
                    . '            └── python' . PHP_EOL
                    . PHP_EOL . '6 directories, 7 files' . PHP_EOL,
            ],
            'visit sub dir' => [
                'structure' => $structure,
                'path' => '/usr/local',
                'options' => [],
                'expected' => '/usr/local' . PHP_EOL
                    . '└── bin' . PHP_EOL
                    . '    ├── composer' . PHP_EOL
                    . '    ├── php' . PHP_EOL
                    . '    └── python' . PHP_EOL
                    . PHP_EOL . '1 directory, 3 files' . PHP_EOL,
            ],
            'visit file' => [
                'structure' => $structure,
                'path' => '/usr/local/bin/php',
                'options' => [],
                'expected' => '/usr/local/bin/php' . PHP_EOL
                    . PHP_EOL . '0 directories, 0 files' . PHP_EOL,
            ],
            'spacing = 1' => [
                'structure' => $structure,
                'path' => null,
                'options' => ['spacing' => 1],
                'expected' => 'mfs://' . PHP_EOL
                    . '│' . PHP_EOL
                    . '└── /' . PHP_EOL
                    . '    │' . PHP_EOL
                    . '    ├── dev' . PHP_EOL
                    . '    │   │' . PHP_EOL
                    . '    │   └── null' . PHP_EOL
                    . '    │' . PHP_EOL
                    . '    ├── etc' . PHP_EOL
                    . '    │   │' . PHP_EOL
                    . '    │   ├── passwd' . PHP_EOL
                    . '    │   │' . PHP_EOL
                    . '    │   ├── shadow' . PHP_EOL
                    . '    │   │' . PHP_EOL
                    . '    │   └── ssh' . PHP_EOL
                    . '    │       │' . PHP_EOL
                    . '    │       └── ssh_config' . PHP_EOL
                    . '    │' . PHP_EOL
                    . '    └── usr' . PHP_EOL
                    . '        │' . PHP_EOL
                    . '        └── local' . PHP_EOL
                    . '            │' . PHP_EOL
                    . '            └── bin' . PHP_EOL
                    . '                │' . PHP_EOL
                    . '                ├── composer' . PHP_EOL
                    . '                │' . PHP_EOL
                    . '                ├── php' . PHP_EOL
                    . '                │' . PHP_EOL
                    . '                └── python' . PHP_EOL
                    . PHP_EOL . '6 directories, 7 files' . PHP_EOL,
            ],
            'spacing = 2' => [
                'structure' => $structure,
                'path' => null,
                'options' => ['spacing' => 2],
                'expected' => 'mfs://' . PHP_EOL
                    . '│' . PHP_EOL
                    . '│' . PHP_EOL
                    . '└── /' . PHP_EOL
                    . '    │' . PHP_EOL
                    . '    │' . PHP_EOL
                    . '    ├── dev' . PHP_EOL
                    . '    │   │' . PHP_EOL
                    . '    │   │' . PHP_EOL
                    . '    │   └── null' . PHP_EOL
                    . '    │' . PHP_EOL
                    . '    │' . PHP_EOL
                    . '    ├── etc' . PHP_EOL
                    . '    │   │' . PHP_EOL
                    . '    │   │' . PHP_EOL
                    . '    │   ├── passwd' . PHP_EOL
                    . '    │   │' . PHP_EOL
                    . '    │   │' . PHP_EOL
                    . '    │   ├── shadow' . PHP_EOL
                    . '    │   │' . PHP_EOL
                    . '    │   │' . PHP_EOL
                    . '    │   └── ssh' . PHP_EOL
                    . '    │       │' . PHP_EOL
                    . '    │       │' . PHP_EOL
                    . '    │       └── ssh_config' . PHP_EOL
                    . '    │' . PHP_EOL
                    . '    │' . PHP_EOL
                    . '    └── usr' . PHP_EOL
                    . '        │' . PHP_EOL
                    . '        │' . PHP_EOL
                    . '        └── local' . PHP_EOL
                    . '            │' . PHP_EOL
                    . '            │' . PHP_EOL
                    . '            └── bin' . PHP_EOL
                    . '                │' . PHP_EOL
                    . '                │' . PHP_EOL
                    . '                ├── composer' . PHP_EOL
                    . '                │' . PHP_EOL
                    . '                │' . PHP_EOL
                    . '                ├── php' . PHP_EOL
                    . '                │' . PHP_EOL
                    . '                │' . PHP_EOL
                    . '                └── python' . PHP_EOL
                    . PHP_EOL . '6 directories, 7 files' . PHP_EOL,
            ],
            'options' => [
                'structure' => $structure,
                'path' => null,
                'options' => [
                    'trunk' => '||',
                    'trunkBranch' => '||',
                    'trunkEnd' => '`-',
                    'branchPrefix' => '-->(',
                    'branchSuffix' => ')',
                    'headerPrefix' => '─ ',
                    'headerSuffix' => ' ─',
                    'spacing' => 1,
                ],
                'expected' => '─ mfs:// ─' . PHP_EOL
                    . '  ||' . PHP_EOL
                    . '  `--->(/)' . PHP_EOL
                    . '        ||' . PHP_EOL
                    . '        ||-->(dev)' . PHP_EOL
                    . '        ||    ||' . PHP_EOL
                    . '        ||    `--->(null)' . PHP_EOL
                    . '        ||' . PHP_EOL
                    . '        ||-->(etc)' . PHP_EOL
                    . '        ||    ||' . PHP_EOL
                    . '        ||    ||-->(passwd)' . PHP_EOL
                    . '        ||    ||' . PHP_EOL
                    . '        ||    ||-->(shadow)' . PHP_EOL
                    . '        ||    ||' . PHP_EOL
                    . '        ||    `--->(ssh)' . PHP_EOL
                    . '        ||          ||' . PHP_EOL
                    . '        ||          `--->(ssh_config)' . PHP_EOL
                    . '        ||' . PHP_EOL
                    . '        `--->(usr)' . PHP_EOL
                    . '              ||' . PHP_EOL
                    . '              `--->(local)' . PHP_EOL
                    . '                    ||' . PHP_EOL
                    . '                    `--->(bin)' . PHP_EOL
                    . '                          ||' . PHP_EOL
                    . '                          ||-->(composer)' . PHP_EOL
                    . '                          ||' . PHP_EOL
                    . '                          ||-->(php)' . PHP_EOL
                    . '                          ||' . PHP_EOL
                    . '                          `--->(python)' . PHP_EOL
                    . PHP_EOL . '6 directories, 7 files' . PHP_EOL,
            ],
        ];
    }

    /**
     * @dataProvider sampleOnePartitionInsideAnother
     */
    public function testOnePartitionInsideAnotherPartition(
        string $partitionB,
        array $structureA,
        array $structureB,
        ?string $path,
        array $options,
        string $expected
    ): void {
        $handle = fopen('php://memory', 'w+');
        if ($handle === false) {
            self::fail('Failed to open file handle');
        }
        $visitor = new TreeVisitor($handle, $options);

        MockFileSystem::addStructure($structureA, $this->root);
        MockFileSystem::createPartition($partitionB, null, $structureB)->addTo($this->root);

        $file = null;
        if ($path !== null) {
            $file = MockFileSystem::find($path);
        }
        MockFileSystem::visit($file, $visitor);

        $actual = stream_get_contents($handle, -1, 0);

        self::assertEquals($expected, $actual);
    }

    public function sampleOnePartitionInsideAnother(): array
    {
        $structureA = [
            'dev' => [
                '[null]' => null,
            ],
            'usr' => [
                'local' => [
                    'bin' => [
                        'php' => '',
                        'python' => '',
                        'composer' => '',
                    ],
                ],
            ],
            'etc' => [
                'ssh' => [
                    'ssh_config' => '',
                ],
                'passwd' => '',
                'shadow' => '',
            ],
        ];
        $structureB = [
            'userA' => [
                'docs' => [
                    'example.txt' => '',
                ],
                'music' => [],
                'movies' => [],
            ],
            'userB' => [
                'docs' => [],
                'music' => ['sample.mp3' => ''],
                'movies' => ['movie.mp4' => ''],
            ],
        ];

        return [
            'visit whole file system' => [
                'partitionB' => 'home',
                'structureA' => $structureA,
                'structureB' => $structureB,
                'path' => null,
                'options' => [],
                'expected' => 'mfs://' . PHP_EOL
                    . '├── /' . PHP_EOL
                    . '│   ├── dev' . PHP_EOL
                    . '│   │   └── null' . PHP_EOL
                    . '│   ├── etc' . PHP_EOL
                    . '│   │   ├── passwd' . PHP_EOL
                    . '│   │   ├── shadow' . PHP_EOL
                    . '│   │   └── ssh' . PHP_EOL
                    . '│   │       └── ssh_config' . PHP_EOL
                    . '│   ├── home -> /home' . PHP_EOL
                    . '│   └── usr' . PHP_EOL
                    . '│       └── local' . PHP_EOL
                    . '│           └── bin' . PHP_EOL
                    . '│               ├── composer' . PHP_EOL
                    . '│               ├── php' . PHP_EOL
                    . '│               └── python' . PHP_EOL
                    . '└── /home' . PHP_EOL
                    . '    ├── userA' . PHP_EOL
                    . '    │   ├── docs' . PHP_EOL
                    . '    │   │   └── example.txt' . PHP_EOL
                    . '    │   ├── movies' . PHP_EOL
                    . '    │   └── music' . PHP_EOL
                    . '    └── userB' . PHP_EOL
                    . '        ├── docs' . PHP_EOL
                    . '        ├── movies' . PHP_EOL
                    . '        │   └── movie.mp4' . PHP_EOL
                    . '        └── music' . PHP_EOL
                    . '            └── sample.mp3' . PHP_EOL
                    . PHP_EOL . '16 directories, 10 files' . PHP_EOL,
            ],
            'visit internal partition' => [
                'partitionB' => 'home',
                'structureA' => $structureA,
                'structureB' => $structureB,
                'path' => '/home',
                'options' => [],
                'expected' => '/home' . PHP_EOL
                    . '├── userA' . PHP_EOL
                    . '│   ├── docs' . PHP_EOL
                    . '│   │   └── example.txt' . PHP_EOL
                    . '│   ├── movies' . PHP_EOL
                    . '│   └── music' . PHP_EOL
                    . '└── userB' . PHP_EOL
                    . '    ├── docs' . PHP_EOL
                    . '    ├── movies' . PHP_EOL
                    . '    │   └── movie.mp4' . PHP_EOL
                    . '    └── music' . PHP_EOL
                    . '        └── sample.mp3' . PHP_EOL
                    . PHP_EOL . '8 directories, 3 files' . PHP_EOL,
            ],
            'options' => [
                'partitionB' => 'home',
                'structureA' => $structureA,
                'structureB' => $structureB,
                'path' => null,
                'options' => ['pointer' => '  ----->  '],
                'expected' => 'mfs://' . PHP_EOL
                    . '├── /' . PHP_EOL
                    . '│   ├── dev' . PHP_EOL
                    . '│   │   └── null' . PHP_EOL
                    . '│   ├── etc' . PHP_EOL
                    . '│   │   ├── passwd' . PHP_EOL
                    . '│   │   ├── shadow' . PHP_EOL
                    . '│   │   └── ssh' . PHP_EOL
                    . '│   │       └── ssh_config' . PHP_EOL
                    . '│   ├── home  ----->  /home' . PHP_EOL
                    . '│   └── usr' . PHP_EOL
                    . '│       └── local' . PHP_EOL
                    . '│           └── bin' . PHP_EOL
                    . '│               ├── composer' . PHP_EOL
                    . '│               ├── php' . PHP_EOL
                    . '│               └── python' . PHP_EOL
                    . '└── /home' . PHP_EOL
                    . '    ├── userA' . PHP_EOL
                    . '    │   ├── docs' . PHP_EOL
                    . '    │   │   └── example.txt' . PHP_EOL
                    . '    │   ├── movies' . PHP_EOL
                    . '    │   └── music' . PHP_EOL
                    . '    └── userB' . PHP_EOL
                    . '        ├── docs' . PHP_EOL
                    . '        ├── movies' . PHP_EOL
                    . '        │   └── movie.mp4' . PHP_EOL
                    . '        └── music' . PHP_EOL
                    . '            └── sample.mp3' . PHP_EOL
                    . PHP_EOL . '16 directories, 10 files' . PHP_EOL,
            ],
        ];
    }

    /**
     * @dataProvider sampleMultiplePartitions
     */
    public function testMultiplePartitions(
        string $partitionA,
        string $partitionB,
        array $structureA,
        array $structureB,
        ?string $path,
        string $expected
    ): void {
        $handle = fopen('php://memory', 'w+');
        if ($handle === false) {
            self::fail('Failed to open file handle');
        }
        $visitor = new TreeVisitor($handle);

        $this->root->setName($partitionA);
        MockFileSystem::addStructure($structureA, $this->root);
        MockFileSystem::createPartition($partitionB, null, $structureB)->addTo(MockFileSystem::getFileSystem());

        $file = null;
        if ($path !== null) {
            $file = MockFileSystem::find($path);
        }
        MockFileSystem::visit($file, $visitor);

        $actual = stream_get_contents($handle, -1, 0);

        self::assertEquals($expected, $actual);
    }

    public function sampleMultiplePartitions(): array
    {
        $structureA = [
            'dev' => [
                '[null]' => null,
            ],
            'usr' => [
                'local' => [
                    'bin' => [
                        'php' => '',
                        'python' => '',
                        'composer' => '',
                    ],
                ],
            ],
            'etc' => [
                'ssh' => [
                    'ssh_config' => '',
                ],
                'passwd' => '',
                'shadow' => '',
            ],
        ];
        $structureB = [
            'userA' => [
                'docs' => [
                    'example.txt' => '',
                ],
                'music' => [],
                'movies' => [],
            ],
            'userB' => [
                'docs' => [],
                'music' => ['sample.mp3' => ''],
                'movies' => ['movie.mp4' => ''],
            ],
        ];

        return [
            'multiple partitions, visit whole file system' => [
                'partitionA' => 'C:',
                'partitionB' => 'D:',
                'structureA' => $structureA,
                'structureB' => $structureB,
                'path' => null,
                'expected' => 'mfs://' . PHP_EOL
                    . '├── C:/' . PHP_EOL
                    . '│   ├── dev' . PHP_EOL
                    . '│   │   └── null' . PHP_EOL
                    . '│   ├── etc' . PHP_EOL
                    . '│   │   ├── passwd' . PHP_EOL
                    . '│   │   ├── shadow' . PHP_EOL
                    . '│   │   └── ssh' . PHP_EOL
                    . '│   │       └── ssh_config' . PHP_EOL
                    . '│   └── usr' . PHP_EOL
                    . '│       └── local' . PHP_EOL
                    . '│           └── bin' . PHP_EOL
                    . '│               ├── composer' . PHP_EOL
                    . '│               ├── php' . PHP_EOL
                    . '│               └── python' . PHP_EOL
                    . '└── D:/' . PHP_EOL
                    . '    ├── userA' . PHP_EOL
                    . '    │   ├── docs' . PHP_EOL
                    . '    │   │   └── example.txt' . PHP_EOL
                    . '    │   ├── movies' . PHP_EOL
                    . '    │   └── music' . PHP_EOL
                    . '    └── userB' . PHP_EOL
                    . '        ├── docs' . PHP_EOL
                    . '        ├── movies' . PHP_EOL
                    . '        │   └── movie.mp4' . PHP_EOL
                    . '        └── music' . PHP_EOL
                    . '            └── sample.mp3' . PHP_EOL
                    . PHP_EOL . '15 directories, 10 files' . PHP_EOL,
            ],
            'multiple partitions, visit sub dir of one' => [
                'partitionA' => 'C:',
                'partitionB' => 'D:',
                'structureA' => $structureA,
                'structureB' => $structureB,
                'path' => 'D:/userA',
                'expected' => 'D:/userA' . PHP_EOL
                    . '├── docs' . PHP_EOL
                    . '│   └── example.txt' . PHP_EOL
                    . '├── movies' . PHP_EOL
                    . '└── music' . PHP_EOL
                    . PHP_EOL . '3 directories, 1 file' . PHP_EOL,
            ],
        ];
    }
}
