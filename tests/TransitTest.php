<?php
namespace Atlas\Transit;

use Atlas\Orm\Atlas;
use Atlas\Testing\DataSource\Author\AuthorRecord;
use Atlas\Testing\DataSource\Author\AuthorRecordSet;
use Atlas\Testing\DataSourceFixture;
use Atlas\Transit\Domain\Aggregate\Discussion;
use Atlas\Transit\Domain\Entity\Author\Author;
use Atlas\Transit\Domain\Entity\Author\AuthorCollection;
use Atlas\Transit\Domain\Entity\Reply\Reply;
use Atlas\Transit\Domain\Entity\Reply\ReplyCollection;
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
        $this->transit = Transit::new(
            $this->atlas,
            'Atlas\\Testing\\DataSource\\',
            'Atlas\\Transit\\Domain\\'
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
            'subject' => 'Thread subject 1',
            'body' => 'Thread body 1',
            'author' => [
                'authorId' => 1,
                'name' => 'Anna',
                'email' => ['email' => 'anna@example.com']
            ],
            'createdAt' => ['date' => '1970-08-08', 'time' => '00:00:00'],
        ];
        $this->assertEquals($expect, $actual);

        $threadEntity->setSubject('CHANGED SUBJECT');

        $expect = [
            'threadId' => 1,
            'subject' => 'CHANGED SUBJECT',
            'body' => 'Thread body 1',
            'author' => [
                'authorId' => 1,
                'name' => 'Anna',
                'email' => ['email' => 'anna@example.com']
            ],
            'createdAt' => ['date' => '1970-08-08', 'time' => '00:00:00'],
        ];
        $actual = $threadEntity->getArrayCopy();

        $this->assertEquals($expect, $actual);

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
            'taggings' => null
        ];
        $actual = $threadRecord->getArrayCopy();
        $this->assertSame($expect, $actual);

        // new entity
        $newThread = new Thread(
            $threadEntity->author,
            new DateTime('1970-08-08'),
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
                'subject' => 'Thread subject 1',
                'body' => 'Thread body 1',
                'author' => [
                    'authorId' => 1,
                    'name' => 'Anna',
                    'email' => ['email' => 'anna@example.com']
                ],
                'createdAt' => ['date' => '1970-08-08', 'time' => '00:00:00'],
            ],
            1 => [
                'threadId' => 2,
                'subject' => 'Thread subject 2',
                'body' => 'Thread body 2',
                'author' => [
                    'authorId' => 2,
                    'name' => 'Betty',
                    'email' => ['email' => 'betty@example.com']
                ],
                'createdAt' => ['date' => '1970-08-08', 'time' => '00:00:00'],
            ],
            2 => [
                'threadId' => 3,
                'subject' => 'Thread subject 3',
                'body' => 'Thread body 3',
                'author' => [
                    'authorId' => 3,
                    'name' => 'Clara',
                    'email' => ['email' => 'clara@example.com']
                ],
                'createdAt' => ['date' => '1970-08-08', 'time' => '00:00:00'],
            ],
        ];
        $actual = $threadCollection->getArrayCopy();
        $this->assertEquals($expect, $actual);

        foreach ($threadCollection as $threadEntity) {
            $threadEntity->setSubject('CHANGE subject ' . $threadEntity->getId());
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
            ],
        ];

        $actual = $threadRecordSet->getArrayCopy();
        $this->assertSame($expect, $actual);
    }

    public function testAggregate()
    {
        $discussionAggregate = $this->transit
            ->select(Discussion::CLASS)
            ->where('thread_id = ', 1)
            ->with([
                'author',
                'replies' => [
                    'author',
                ],
            ])
            ->fetchDomain();

        $expect = [
            'thread' => [
                'threadId' => 1,
                'createdAt' => ['date' => '1970-08-08', 'time' => '00:00:00'],
                'subject' => 'Thread subject 1',
                'body' => 'Thread body 1',
                'author' => [
                    'authorId' => 1,
                    'name' => 'Anna',
                    'email' => ['email' => 'anna@example.com'],
                ],
            ],
            'replies' => [
                0 => [
                    'replyId' => 1,
                    'createdAt' => ['date' => '1979-11-07', 'time' => '00:00:00'],
                    'body' => 'Reply 1 on thread 1',
                    'author' => [
                        'authorId' => 2,
                        'name' => 'Betty',
                        'email' => ['email' => 'betty@example.com'],
                    ],
                ],
                1 => [
                    'replyId' => 2,
                    'createdAt' => ['date' => '1979-11-07', 'time' => '00:00:00'],
                    'body' => 'Reply 2 on thread 1',
                    'author' => [
                        'authorId' => 3,
                        'name' => 'Clara',
                        'email' => ['email' => 'clara@example.com'],
                    ],
                ],
                2 => [
                    'replyId' => 3,
                    'createdAt' => ['date' => '1979-11-07', 'time' => '00:00:00'],
                    'body' => 'Reply 3 on thread 1',
                    'author' => [
                        'authorId' => 4,
                        'name' => 'Donna',
                        'email' => ['email' => 'donna@example.com'],
                    ],
                ],
                3 => [
                    'replyId' => 4,
                    'createdAt' => ['date' => '1979-11-07', 'time' => '00:00:00'],
                    'body' => 'Reply 4 on thread 1',
                    'author' => [
                        'authorId' => 5,
                        'name' => 'Edna',
                        'email' => ['email' => 'edna@example.com'],
                    ],
                ],
                4 => [
                    'replyId' => 5,
                    'createdAt' => ['date' => '1979-11-07', 'time' => '00:00:00'],
                    'body' => 'Reply 5 on thread 1',
                    'author' => [
                        'authorId' => 6,
                        'name' => 'Fiona',
                        'email' => ['email' => 'fiona@example.com'],
                    ],
                ],
            ],
        ];

        $actual = $discussionAggregate->getArrayCopy();
        $this->assertEquals($expect, $actual);

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
            'author' =>  [
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
            'taggings' => NULL,
        ];
        $actual = $threadRecord->getArrayCopy();
        $this->assertSame($expect, $actual);
    }


    public function testNewEntitySource()
    {
        $newAuthor = new Author('Arthur', new Email('arthur@example.com'));
        $this->transit->store($newAuthor);
        $this->transit->persist();

        $newRecord = $this->transit->getStorage()[$newAuthor];
        $this->assertInstanceOf(AuthorRecord::CLASS, $newRecord);
    }

    public function testUpdateSource_newCollection()
    {
        $author = new Author('Arthur', new Email('arthur@example.com'));

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
        $author = new Author('Arthur', new Email('arthur@example.com'));
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
        $threadAuthor = new Author('Thread Author', new Email('threadAuthor@example.com'));

        $thread = new Thread(
            $threadAuthor,
            new DateTime('1970-08-08'),
            'New Thread Subject',
            'New thread body'
        );

        $replyAuthor = new Author('Reply Author', new Email('replyAuthor@example.com'));

        $reply = new Reply(
            $replyAuthor,
            new DateTime('1979-11-07'),
            'New reply body'
        );

        $replies = new ReplyCollection([$reply]);

        $discussion = new Discussion($thread, $replies);

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
        $this->assertSame(101, $actual['replies'][0]['replyId']);
        $this->assertSame(14, $actual['replies'][0]['author']['authorId']);
    }
}
