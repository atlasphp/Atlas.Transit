<?php
declare(strict_types=1);

namespace Atlas\Transit;

use Atlas\Orm\Atlas;
use Atlas\Testing\DataSource\Author\AuthorRecord;
use Atlas\Testing\DataSource\Author\AuthorRecordSet;
use Atlas\Testing\DataSourceFixture;
use Atlas\Transit\Domain\Aggregate\Discussion;
use Atlas\Transit\Domain\Entity\Author\Author;
use Atlas\Transit\Domain\Entity\Author\AuthorCollection;
use Atlas\Transit\Domain\Entity\Response\Response;
use Atlas\Transit\Domain\Entity\Response\Responses;
use Atlas\Transit\Domain\Entity\Tag\Tag;
use Atlas\Transit\Domain\Entity\Tag\TagCollection;
use Atlas\Transit\Domain\Entity\Thread\Thread;
use Atlas\Transit\Domain\Entity\Thread\ThreadCollection;
use Atlas\Transit\Domain\Value\DateTime;
use Atlas\Transit\Domain\Value\Email;
use Atlas\Transit\Transit;
use DateTimeImmutable;
use DateTimeZone;

class TransitTest extends \PHPUnit\Framework\TestCase
{
    protected $transit;

    public function setUp()
    {
        $this->connection = (new DataSourceFixture())->exec();
        $this->atlas = Atlas::new($this->connection);
        $this->transit = FakeTransit::new(
            $this->atlas,
            'Atlas\\Testing\\DataSource\\'
        );
    }

    public function testEntity()
    {
        $threadEntity = $this->transit
            ->select(Thread::CLASS)
            ->where('thread_id = ', 1)
            ->with(['author'])
            ->fetchDomain();

        $actual = $threadEntity->getArrayCopy();
        $expect = [
            'threadId' => 1,
            'author' => [
                'authorId' => 1,
                'name' => 'Anna',
            ],
            'subject' => 'Thread subject 1',
            'body' => 'Thread body 1',
        ];
        $this->assertSame($expect, $actual);

        $threadEntity->setSubject('CHANGED SUBJECT');

        $expect = [
            'threadId' => 1,
            'author' => [
                'authorId' => 1,
                'name' => 'Anna',
            ],
            'subject' => 'CHANGED SUBJECT',
            'body' => 'Thread body 1',
        ];
        $actual = $threadEntity->getArrayCopy();

        $this->assertSame($expect, $actual);

        $this->transit->store($threadEntity);
        $this->transit->persist();

        $threadRecord = $this->transit->getStorage()[$threadEntity];

        $expect = [
            'thread_id' => 1,
            'author_id' => 1,
            'subject' => 'CHANGED SUBJECT',
            'body' => 'Thread body 1',
            'author' => [
                'author_id' => 1,
                'name' => 'Anna',
                'replies' => null,
                'threads' => null,
            ],
            'summary' => null,
            'replies' => null,
            'taggings' => null,
            'tags' => null,
        ];
        $actual = $threadRecord->getArrayCopy();
        $this->assertSame($expect, $actual);

        // new entity
        $newThread = new Thread(
            $threadEntity->author,
            'New Subject',
            'New Body'
        );

        $this->transit->store($newThread);
        $this->transit->persist();

        $this->assertSame(21, $newThread->threadId);
    }

    public function testCollection()
    {
        $threadCollection = $this->transit
            ->select(ThreadCollection::CLASS)
            ->where('thread_id IN ', [1, 2, 3])
            ->with(['author'])
            ->fetchDomain();

        $threadRecordSet = $this->transit->getStorage()[$threadCollection];

        $expect = [
            0 => [
                'threadId' => 1,
                'author' => [
                    'authorId' => 1,
                    'name' => 'Anna',
                ],
                'subject' => 'Thread subject 1',
                'body' => 'Thread body 1',
            ],
            1 => [
                'threadId' => 2,
                'author' => [
                    'authorId' => 2,
                    'name' => 'Betty',
                ],
                'subject' => 'Thread subject 2',
                'body' => 'Thread body 2',
            ],
            2 => [
                'threadId' => 3,
                'author' => [
                    'authorId' => 3,
                    'name' => 'Clara',
                ],
                'subject' => 'Thread subject 3',
                'body' => 'Thread body 3',
            ],
        ];
        $actual = $threadCollection->getArrayCopy();
        $this->assertSame($expect, $actual);

        foreach ($threadCollection as $threadEntity) {
            $threadEntity->setSubject('CHANGE subject ' . $threadEntity->threadId);
        }

        $this->transit->store($threadCollection);
        $this->transit->persist();

        $expect = [
            [
                'thread_id' => 1,
                'author_id' => 1,
                'subject' => 'CHANGE subject 1',
                'body' => 'Thread body 1',
                'author' => [
                    'author_id' => 1,
                    'name' => 'Anna',
                    'replies' => NULL,
                    'threads' => NULL,
                ],
                'summary' => NULL,
                'replies' => NULL,
                'taggings' => NULL,
                'tags' => NULL,
            ],
            [
                'thread_id' => 2,
                'author_id' => 2,
                'subject' => 'CHANGE subject 2',
                'body' => 'Thread body 2',
                'author' => [
                    'author_id' => 2,
                    'name' => 'Betty',
                    'replies' => NULL,
                    'threads' => NULL,
                ],
                'summary' => NULL,
                'replies' => NULL,
                'taggings' => NULL,
                'tags' => NULL,
            ],
            [
                'thread_id' => 3,
                'author_id' => 3,
                'subject' => 'CHANGE subject 3',
                'body' => 'Thread body 3',
                'author' => [
                    'author_id' => 3,
                    'name' => 'Clara',
                    'replies' => NULL,
                    'threads' => NULL,
                ],
                'summary' => NULL,
                'replies' => NULL,
                'taggings' => NULL,
                'tags' => NULL,
            ],
        ];

        $actual = $threadRecordSet->getArrayCopy();
        $this->assertSame($expect, $actual);
    }

    public function testAggregate()
    {
        $discussionAggregate = $this->transit
            ->select(Discussion::CLASS, ['thread_id' => 1])
            ->with([
                'author',
                'replies' => [
                    'author',
                ],
                'tags'
            ])
            ->fetchDomain();

        $expect = [
            'thread' => [
                'threadId' => 1,
                'author' => [
                    'authorId' => 1,
                    'name' => 'Anna',
                ],
                'subject' => 'Thread subject 1',
                'body' => 'Thread body 1',
            ],
            'tags' => [
                0 => [
                    'tagId' => 1,
                    'name' => 'foo',
                ],
                1 => [
                    'tagId' => 2,
                    'name' => 'bar',
                ],
                2 => [
                    'tagId' => 3,
                    'name' => 'baz',
                ],
            ],
            'responses' => [
                0 => [
                    'responseId' => 1,
                    'author' => [
                        'authorId' => 2,
                        'name' => 'Betty',
                    ],
                    'body' => 'Reply 1 on thread 1',
                ],
                1 => [
                    'responseId' => 2,
                    'author' => [
                        'authorId' => 3,
                        'name' => 'Clara',
                    ],
                    'body' => 'Reply 2 on thread 1',
                ],
                2 => [
                    'responseId' => 3,
                    'author' => [
                        'authorId' => 4,
                        'name' => 'Donna',
                    ],
                    'body' => 'Reply 3 on thread 1',
                ],
                3 => [
                    'responseId' => 4,
                    'author' => [
                        'authorId' => 5,
                        'name' => 'Edna',
                    ],
                    'body' => 'Reply 4 on thread 1',
                ],
                4 => [
                    'responseId' => 5,
                    'author' => [
                        'authorId' => 6,
                        'name' => 'Fiona',
                    ],
                    'body' => 'Reply 5 on thread 1',
                ],
            ],
        ];

        $actual = $discussionAggregate->getArrayCopy();
        $this->assertSame($expect, $actual);

        $discussionAggregate->setThreadSubject('CHANGED SUBJECT');
        $expect['thread']['subject'] = 'CHANGED SUBJECT';
        $actual = $discussionAggregate->getArrayCopy();
        $this->assertEquals($expect, $actual);

        $this->transit->store($discussionAggregate);
        $this->transit->persist();

        $threadRecord = $this->transit->getStorage()[$discussionAggregate];

        $expect = [
            'thread_id' => 1,
            'author_id' => 1,
            'subject' => 'CHANGED SUBJECT',
            'body' => 'Thread body 1',
            'author' => [
                'author_id' => 1,
                'name' => 'Anna',
                'replies' => NULL,
                'threads' => NULL,
            ],
            'summary' => NULL,
            'replies' => [
                0 => [
                    'reply_id' => 1,
                    'thread_id' => 1,
                    'author_id' => 2,
                    'body' => 'Reply 1 on thread 1',
                    'author' => [
                        'author_id' => 2,
                        'name' => 'Betty',
                        'replies' => NULL,
                        'threads' => NULL,
                    ],
                ],
                1 => [
                    'reply_id' => 2,
                    'thread_id' => 1,
                    'author_id' => 3,
                    'body' => 'Reply 2 on thread 1',
                    'author' => [
                        'author_id' => 3,
                        'name' => 'Clara',
                        'replies' => NULL,
                        'threads' => NULL,
                    ],
                ],
                2 => [
                    'reply_id' => 3,
                    'thread_id' => 1,
                    'author_id' => 4,
                    'body' => 'Reply 3 on thread 1',
                    'author' => [
                        'author_id' => 4,
                        'name' => 'Donna',
                        'replies' => NULL,
                        'threads' => NULL,
                      ],
                ],
                3 => [
                    'reply_id' => 4,
                    'thread_id' => 1,
                    'author_id' => 5,
                    'body' => 'Reply 4 on thread 1',
                    'author' => [
                        'author_id' => 5,
                        'name' => 'Edna',
                        'replies' => NULL,
                        'threads' => NULL,
                    ],
                ],
                4 => [
                    'reply_id' => 5,
                    'thread_id' => 1,
                    'author_id' => 6,
                    'body' => 'Reply 5 on thread 1',
                    'author' => [
                        'author_id' => 6,
                        'name' => 'Fiona',
                        'replies' => NULL,
                        'threads' => NULL,
                    ],
                ],
            ],
            'taggings' => [
                0 => [
                    'tagging_id' => '1',
                    'thread_id' => 1,
                    'tag_id' => '1',
                    'thread' => NULL,
                    'tag' => NULL,
                ],
                1 => [
                    'tagging_id' => '2',
                    'thread_id' => 1,
                    'tag_id' => '2',
                    'thread' => NULL,
                    'tag' => NULL,
                ],
                2 => [
                    'tagging_id' => '3',
                    'thread_id' => 1,
                    'tag_id' => '3',
                    'thread' => NULL,
                    'tag' => NULL,
                ],
            ],
            'tags' => [
                0 => [
                    'tag_id' => '1',
                    'name' => 'foo',
                    'taggings' => NULL,
                ],
                1 => [
                    'tag_id' => '2',
                    'name' => 'bar',
                    'taggings' => NULL,
                ],
                2 => [
                    'tag_id' => '3',
                    'name' => 'baz',
                    'taggings' => NULL,
                ],
            ],
        ];

        $actual = $threadRecord->getArrayCopy();
        $this->assertEquals($expect, $actual);
    }

    public function testNewEntitySource()
    {
        $newAuthor = new Author('Arthur');
        $this->transit->store($newAuthor);
        $this->transit->persist();

        $newRecord = $this->transit->getStorage()[$newAuthor];
        $this->assertInstanceOf(AuthorRecord::CLASS, $newRecord);
    }

    public function testUpdateSource_newCollection()
    {
        $author = new Author('Arthur');

        $authorCollection = new AuthorCollection([$author]);

        $this->transit->store($authorCollection);
        $this->transit->persist();

        $authorRecordSet = $this->transit->getStorage()[$authorCollection];
        $this->assertInstanceOf(AuthorRecordSet::CLASS, $authorRecordSet);
        $this->assertInstanceOf(AuthorRecord::CLASS, $authorRecordSet[0]);
        $this->assertSame('13', $authorRecordSet[0]->author_id);
        $this->assertSame(13, $author->authorId);
    }

    public function testDiscard_noDomain()
    {
        $author = new Author('Arthur');
        $this->transit->discard($author);

        $this->expectException(Exception::CLASS);
        $this->expectExceptionMessage('no source for domain');
        $this->transit->persist();
    }

    public function testDiscard_entity()
    {
        $author = $this->transit
            ->select(Author::CLASS)
            ->where('author_id = ', 1)
            ->fetchDomain();

        $this->transit->discard($author);
        $this->transit->persist();

        $record = $this->transit->getStorage()[$author];
        $this->assertSame('DELETED', $record->getRow()->getStatus());
    }

    public function testDiscard_Collection()
    {
        $authorCollection = $this->transit
            ->select(AuthorCollection::CLASS)
            ->where('author_id IN ', [1, 2, 3])
            ->fetchDomain();

        $this->transit->discard($authorCollection);
        $this->transit->persist();

        $recordSet = $this->transit->getStorage()[$authorCollection];
        foreach ($recordSet as $record) {
            $this->assertSame('DELETED', $record->getRow()->getStatus());
        }
    }

    public function testStore()
    {
        /* Create entirely new aggregate */
        $threadAuthor = new Author('Thread Author');

        $thread = new Thread(
            $threadAuthor,
            'New Thread Subject',
            'New thread body'
        );

        $responseAuthor = new Author('Reply Author');

        $response = new Response(
            $responseAuthor,
            'New reply body'
        );

        $responses = new Responses([$response]);

        $tag = new Tag('new_name');
        $tags = new TagCollection([$tag]);

        $discussion = new Discussion($thread, $tags, $responses);

        /* plan to store the aggregate */
        $this->transit->store($discussion);
        $plan = $this->transit->getPlan();
        $this->assertTrue($plan->contains($discussion));

        /* execute the persistence plan */
        $this->transit->persist();

        /* did the aggregate components get refreshed with autoinc values? */
        $actual = $discussion->getArrayCopy();
        $this->assertSame(21, $actual['thread']['threadId']);
        $this->assertSame(13, $actual['thread']['author']['authorId']);
        $this->assertSame(6, $actual['tags'][0]['tagId']);
        $this->assertSame(101, $actual['responses'][0]['responseId']);
        $this->assertSame(14, $actual['responses'][0]['author']['authorId']);
    }

    public function testAttach()
    {
        // Creation of author in first context
        $entity = new Author('Author');
        $this->transit->store($entity);
        $this->transit->persist();

        // New transit class for new context
        $secondTransit = FakeTransit::new(
            $this->atlas,
            'Atlas\\Testing\\DataSource\\'
        );
        $secondTransit->attach($entity);

        // Modify entity after attach to context
        $entity->setName('Arthur 2123 fdgfd');

        $secondTransit->store($entity);
        $secondTransit->persist();

        /** @var \Atlas\Mapper\Record $record */
        $record = $secondTransit->getStorage()->offsetGet($entity);
        $this->assertEquals('UPDATED', $record->getRow()->getStatus());
    }
}
