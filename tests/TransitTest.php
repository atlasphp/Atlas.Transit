<?php
namespace Atlas\Transit;

use Atlas\Transit\Domain\Entity\Author\Author;
use Atlas\Transit\Domain\Entity\Author\AuthorCollection;
use Atlas\Transit\Domain\Entity\Dated\Dated;
use Atlas\Transit\Domain\Entity\Named\Named;
use Atlas\Transit\Domain\Entity\Reply\Reply;
use Atlas\Transit\Domain\Entity\Reply\ReplyCollection;
use Atlas\Transit\Domain\Entity\Thread\Thread;
use Atlas\Transit\Domain\Entity\Thread\ThreadCollection;
use Atlas\Transit\Transit;
use Atlas\Testing\DataSourceFixture;
use Atlas\Orm\Atlas;
use DateTimeImmutable;
use DateTimeZone;

class TransitTest extends \PHPUnit\Framework\TestCase
{
    protected $transit;

    public function setUp()
    {
        $this->connection = (new DataSourceFixture())->exec();
        $this->atlas = Atlas::new($this->connection);
        $this->transit = new Transit(
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

        $threadRecord = $this->transit->getStorage()[$threadEntity];

        $actual = $threadEntity->getArrayCopy();
        $expect = [
            'threadId' => 1,
            'subject' => 'Thread subject 1',
            'body' => 'Thread body 1',
            'author' => [
                'authorId' => 1,
                'name' => 'Anna',
            ],
        ];
        $this->assertSame($expect, $actual);

        $threadEntity->setSubject('CHANGED SUBJECT');

        $expect = [
            'threadId' => 1,
            'subject' => 'CHANGED SUBJECT',
            'body' => 'Thread body 1',
            'author' => [
                'authorId' => 1,
                'name' => 'Anna',
            ],
        ];
        $actual = $threadEntity->getArrayCopy();

        $this->assertSame($expect, $actual);

        $this->transit->store($threadEntity);
        $this->transit->persist();

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
    }

    // public function testEntityCollection()
    // {
    //     $threadEntityCollection = $this->transit
    //         ->select(ThreadEntityCollection::CLASS)
    //         ->where('thread_id IN (?)', [1, 2, 3])
    //         ->with(['author'])
    //         ->fetchDomain();

    //     $threadRecordSet = $this->transit->getStorage()[$threadEntityCollection];

    //     $expect = [
    //         0 => [
    //             'threadId' => 1,
    //             'subject' => 'Thread subject 1',
    //             'body' => 'Thread body 1',
    //             'author' => [
    //                 'authorId' => 1,
    //                 'name' => 'Anna',
    //             ],
    //         ],
    //         1 => [
    //             'threadId' => 2,
    //             'subject' => 'Thread subject 2',
    //             'body' => 'Thread body 2',
    //             'author' => [
    //                 'authorId' => 2,
    //                 'name' => 'Betty',
    //             ],
    //         ],
    //         2 => [
    //             'threadId' => 3,
    //             'subject' => 'Thread subject 3',
    //             'body' => 'Thread body 3',
    //             'author' => [
    //                 'authorId' => 3,
    //                 'name' => 'Clara',
    //             ],
    //         ],
    //     ];
    //     $actual = $threadEntityCollection->getArrayCopy();
    //     $this->assertSame($expect, $actual);

    //     foreach ($threadEntityCollection as $threadEntity) {
    //         $threadEntity->setSubject('CHANGE subject ' . $threadEntity->getId());
    //     }

    //     $this->transit->store($threadEntityCollection);
    //     $this->transit->persist();

    //     $expect = [
    //         [
    //             'thread_id' => 1,
    //             'author_id' => 1,
    //             'subject' => 'CHANGE subject 1',
    //             'body' => 'Thread body 1',
    //             'author' => [
    //                 'author_id' => 1,
    //                 'name' => 'Anna',
    //                 'replies' => NULL,
    //                 'threads' => NULL,
    //             ],
    //             'summary' => NULL,
    //             'replies' => NULL,
    //             'taggings' => NULL,
    //             'tags' => NULL,
    //         ],
    //         [
    //             'thread_id' => 2,
    //             'author_id' => 2,
    //             'subject' => 'CHANGE subject 2',
    //             'body' => 'Thread body 2',
    //             'author' => [
    //                 'author_id' => 2,
    //                 'name' => 'Betty',
    //                 'replies' => NULL,
    //                 'threads' => NULL,
    //             ],
    //             'summary' => NULL,
    //             'replies' => NULL,
    //             'taggings' => NULL,
    //             'tags' => NULL,
    //         ],
    //         [
    //             'thread_id' => 3,
    //             'author_id' => 3,
    //             'subject' => 'CHANGE subject 3',
    //             'body' => 'Thread body 3',
    //             'author' => [
    //                 'author_id' => 3,
    //                 'name' => 'Clara',
    //                 'replies' => NULL,
    //                 'threads' => NULL,
    //             ],
    //             'summary' => NULL,
    //             'replies' => NULL,
    //             'taggings' => NULL,
    //             'tags' => NULL,
    //         ],
    //     ];

    //     $actual = $threadRecordSet->getArrayCopy();
    //     $this->assertSame($expect, $actual);
    // }

    // public function testAggregate()
    // {
    //     $discussionAggregate = $this->transit
    //         ->select(DiscussionAggregate::CLASS)
    //         ->where('thread_id = ?', 1)
    //         ->with([
    //             'author',
    //             'replies' => [
    //                 'author',
    //             ],
    //         ])
    //         ->fetchDomain();

    //     $threadRecord = $this->transit->getStorage()[$discussionAggregate];

    //     $expect = [
    //         'thread' => [
    //             'threadId' => 1,
    //             'subject' => 'Thread subject 1',
    //             'body' => 'Thread body 1',
    //             'author' => [
    //                 'authorId' => 1,
    //                 'name' => 'Anna',
    //             ],
    //         ],
    //         'replies' => [
    //             0 => [
    //                 'replyId' => 1,
    //                 'body' => 'Reply 1 on thread 1',
    //                 'author' => [
    //                     'authorId' => 2,
    //                     'name' => 'Betty',
    //                 ],
    //             ],
    //             1 => [
    //                 'replyId' => 2,
    //                 'body' => 'Reply 2 on thread 1',
    //                 'author' => [
    //                     'authorId' => 3,
    //                     'name' => 'Clara',
    //                 ],
    //             ],
    //             2 => [
    //                 'replyId' => 3,
    //                 'body' => 'Reply 3 on thread 1',
    //                 'author' => [
    //                     'authorId' => 4,
    //                     'name' => 'Donna',
    //                 ],
    //             ],
    //             3 => [
    //                 'replyId' => 4,
    //                 'body' => 'Reply 4 on thread 1',
    //                 'author' => [
    //                     'authorId' => 5,
    //                     'name' => 'Edna',
    //                 ],
    //             ],
    //             4 => [
    //                 'replyId' => 5,
    //                 'body' => 'Reply 5 on thread 1',
    //                 'author' => [
    //                     'authorId' => 6,
    //                     'name' => 'Fiona',
    //                 ],
    //             ],
    //         ],
    //     ];

    //     $actual = $discussionAggregate->getArrayCopy();
    //     $this->assertSame($expect, $actual);

    //     $discussionAggregate->setThreadSubject('CHANGED SUBJECT');
    //     $actual = $discussionAggregate->getArrayCopy();
    //     $expect = [
    //         'thread' => [
    //             'threadId' => 1,
    //             'subject' => 'CHANGED SUBJECT',
    //             'body' => 'Thread body 1',
    //             'author' => [
    //                 'authorId' => 1,
    //                 'name' => 'Anna',
    //             ],
    //         ],
    //         'replies' => [
    //             0 => [
    //                 'replyId' => 1,
    //                 'body' => 'Reply 1 on thread 1',
    //                 'author' => [
    //                     'authorId' => 2,
    //                     'name' => 'Betty',
    //                 ],
    //             ],
    //             1 => [
    //                 'replyId' => 2,
    //                 'body' => 'Reply 2 on thread 1',
    //                 'author' => [
    //                     'authorId' => 3,
    //                     'name' => 'Clara',
    //                 ],
    //             ],
    //             2 => [
    //                 'replyId' => 3,
    //                 'body' => 'Reply 3 on thread 1',
    //                 'author' => [
    //                     'authorId' => 4,
    //                     'name' => 'Donna',
    //                 ],
    //             ],
    //             3 => [
    //                 'replyId' => 4,
    //                 'body' => 'Reply 4 on thread 1',
    //                 'author' => [
    //                     'authorId' => 5,
    //                     'name' => 'Edna',
    //                 ],
    //             ],
    //             4 => [
    //                 'replyId' => 5,
    //                 'body' => 'Reply 5 on thread 1',
    //                 'author' => [
    //                     'authorId' => 6,
    //                     'name' => 'Fiona',
    //                 ],
    //             ],
    //         ],
    //     ];
    //     $this->assertSame($expect, $actual);

    //     $this->transit->store($discussionAggregate);
    //     $this->transit->persist();

    //     $expect = [
    //         'thread_id' => 1,
    //         'author_id' => 1,
    //         'subject' => 'CHANGED SUBJECT',
    //         'body' => 'Thread body 1',
    //         'author' =>  [
    //             'author_id' => 1,
    //             'name' => 'Anna',
    //             'replies' => NULL,
    //             'threads' => NULL,
    //         ],
    //         'summary' => NULL,
    //         'replies' => [
    //             0 => [
    //                 'reply_id' => 1,
    //                 'thread_id' => 1,
    //                 'author_id' => 2,
    //                 'body' => 'Reply 1 on thread 1',
    //                 'author' => [
    //                     'author_id' => 2,
    //                     'name' => 'Betty',
    //                     'replies' => NULL,
    //                     'threads' => NULL,
    //                 ],
    //             ],
    //             1 => [
    //                 'reply_id' => 2,
    //                 'thread_id' => 1,
    //                 'author_id' => 3,
    //                 'body' => 'Reply 2 on thread 1',
    //                 'author' => [
    //                     'author_id' => 3,
    //                     'name' => 'Clara',
    //                     'replies' => NULL,
    //                     'threads' => NULL,
    //                 ],
    //             ],
    //             2 => [
    //                 'reply_id' => 3,
    //                 'thread_id' => 1,
    //                 'author_id' => 4,
    //                 'body' => 'Reply 3 on thread 1',
    //                 'author' => [
    //                     'author_id' => 4,
    //                     'name' => 'Donna',
    //                     'replies' => NULL,
    //                     'threads' => NULL,
    //                 ],
    //             ],
    //             3 => [
    //                 'reply_id' => 4,
    //                 'thread_id' => 1,
    //                 'author_id' => 5,
    //                 'body' => 'Reply 4 on thread 1',
    //                 'author' => [
    //                     'author_id' => 5,
    //                     'name' => 'Edna',
    //                     'replies' => NULL,
    //                     'threads' => NULL,
    //                 ],
    //             ],
    //             4 => [
    //                 'reply_id' => 5,
    //                 'thread_id' => 1,
    //                 'author_id' => 6,
    //                 'body' => 'Reply 5 on thread 1',
    //                 'author' => [
    //                     'author_id' => 6,
    //                     'name' => 'Fiona',
    //                     'replies' => NULL,
    //                     'threads' => NULL,
    //                 ],
    //             ],
    //         ],
    //         'taggings' => NULL,
    //         'tags' => NULL,
    //     ];
    //     $actual = $threadRecord->getArrayCopy();
    //     $this->assertSame($expect, $actual);
    // }

    // // public function testMapping_closure()
    // // {
    // //     $this->transit->mapEntity(DatedEntity::CLASS, FakeMapper::CLASS)
    // //         ->setDomainFromRecord([
    // //             'date' => function ($record) {
    // //                 return new DateTimeImmutable(
    // //                     $record->datetime,
    // //                     new DateTimeZone($record->timezone)
    // //                 );
    // //             }
    // //         ])
    // //         ->setRecordFromDomain([
    // //             'datetime' => function ($domain) {
    // //                 return $domain->getDate()->format('Y-m-d H:i:s');
    // //             },
    // //             'timezone' => function ($domain) {
    // //                 return $domain->getDate()->format('T');
    // //             },
    // //         ]);

    // //     $datedRecord = $this->atlas->newRecord(FakeMapper::CLASS, [
    // //         'id' => '1',
    // //         'name' => 'foo',
    // //         'datetime' => '1970-09-11 12:34:56',
    // //         'timezone' => 'CDT'
    // //     ]);

    // //     $datedEntity = $this->transit->new(DatedEntity::CLASS, $datedRecord);
    // //     $expect = [
    // //         'id' => 1,
    // //         'name' => 'foo',
    // //         'date' => '1970-09-11 12:34:56 CDT',
    // //     ];
    // //     $actual = $datedEntity->getArrayCopy();
    // //     $this->assertSame($expect, $actual);

    // //     $dateBefore = $datedEntity->getDate();
    // //     $this->assertInstanceOf(DateTimeImmutable::CLASS, $dateBefore);

    // //     $dateAfter = $datedEntity->modifyDate('-9 hours');
    // //     $this->assertInstanceOf(DateTimeImmutable::CLASS, $dateAfter);
    // //     $this->assertNotSame($dateBefore, $dateAfter);

    // //     $expect = [
    // //         'id' => 1,
    // //         'name' => 'foo',
    // //         'date' => '1970-09-11 03:34:56 CDT',
    // //     ];
    // //     $actual = $datedEntity->getArrayCopy();
    // //     $this->assertSame($expect, $actual);

    // //     $this->transit->store($datedEntity);
    // //     $this->transit->persist();

    // //     $actual = $this->transit->getStorage()[$datedEntity];

    // //     $expect = [
    // //         'id' => 1,
    // //         'name' => 'foo',
    // //         'datetime' => '1970-09-11 03:34:56',
    // //         'timezone' => 'CDT',
    // //     ];
    // //     $this->assertSame($expect, $actual->getArrayCopy());
    // // }

    // public function testMapping_string()
    // {
    //     $this->transit->mapEntity(NamedEntity::CLASS, FakeMapper::CLASS, [
    //         'name' => 'full_name',
    //     ]);

    //     $namedRecord = $this->atlas->newRecord(FakeMapper::CLASS, [
    //         'id' => '1',
    //         'full_name' => 'foo',
    //     ]);

    //     $namedEntity = $this->transit->new(NamedEntity::CLASS, $namedRecord);
    //     $expect = [
    //         'id' => 1,
    //         'name' => 'foo',
    //     ];
    //     $actual = $namedEntity->getArrayCopy();
    //     $this->assertSame($expect, $actual);

    //     $namedEntity->setName('bar');

    //     $expect = [
    //         'id' => 1,
    //         'name' => 'bar',
    //     ];
    //     $actual = $namedEntity->getArrayCopy();
    //     $this->assertSame($expect, $actual);

    //     $this->transit->store($namedEntity);
    //     $this->transit->persist();

    //     $actual = $this->transit->getStorage()[$namedEntity];

    //     $expect = [
    //         'id' => 1,
    //         'full_name' => 'bar',
    //     ];
    //     $this->assertSame($expect, $actual->getArrayCopy());
    // }

    // public function testNewEntitySource()
    // {
    //     $newAuthor = new AuthorEntity(0, 'Arthur');
    //     $this->transit->store($newAuthor);
    //     $this->transit->persist();

    //     $newRecord = $this->transit->getStorage()[$newAuthor];
    //     $this->assertInstanceOf(AuthorRecord::CLASS, $newRecord);
    // }

    // public function testUpdateSource_newCollection()
    // {
    //     $authorEntityCollection = new AuthorEntityCollection([
    //         new AuthorEntity(0, 'foo'),
    //     ]);

    //     $this->transit->store($authorEntityCollection);
    //     $this->transit->persist();

    //     $authorRecordSet = $this->transit->getStorage()[$authorEntityCollection];
    //     $this->assertInstanceOf(AuthorRecordSet::CLASS, $authorRecordSet);
    //     $this->assertInstanceOf(AuthorRecord::CLASS, $authorRecordSet[0]);
    //     $this->assertEquals(13, $authorRecordSet[0]->author_id);
    // }

    // public function testDiscard_noDomain()
    // {
    //     $authorEntity = new AuthorEntity(0, 'foo');
    //     $this->transit->discard($authorEntity);

    //     $this->expectException(Exception::CLASS);
    //     $this->expectExceptionMessage('no source for domain');
    //     $this->transit->persist();
    // }

    // public function testDiscard_entity()
    // {
    //     $authorEntity = $this->transit
    //         ->select(AuthorEntity::CLASS)
    //         ->where('author_id = ?', 1)
    //         ->fetchDomain();

    //     $this->transit->discard($authorEntity);
    //     $this->transit->persist();

    //     $authorRecord = $this->transit->getStorage()[$authorEntity];
    //     $this->assertSame('DELETED', $authorRecord->getRow()->getStatus());
    // }

    // public function testDiscard_entityCollection()
    // {
    //     $authorEntityCollection = $this->transit
    //         ->select(AuthorEntityCollection::CLASS)
    //         ->where('author_id IN (?)', [1, 2, 3])
    //         ->fetchDomain();

    //     $this->transit->discard($authorEntityCollection);
    //     $this->transit->persist();

    //     $authorRecordSet = $this->transit->getStorage()[$authorEntityCollection];
    //     foreach ($authorRecordSet as $record) {
    //         $this->assertSame('DELETED', $record->getRow()->getStatus());
    //     }
    // }

    // public function testStore()
    // {
    //     /* Create entirely new aggregate */
    //     $threadAuthor = new AuthorEntity(0, 'Thread Author');

    //     $thread = new ThreadEntity(
    //         0,
    //         'New Thread Subject',
    //         'New thread body',
    //         $threadAuthor
    //     );

    //     $replyAuthor = new AuthorEntity(0, 'Reply Author');

    //     $reply = new ReplyEntity(
    //         0,
    //         'New reply body',
    //         $replyAuthor
    //     );

    //     $replies = new ReplyEntityCollection([$reply]);

    //     $discussionAggregate = new DiscussionAggregate($thread, $replies);

    //     /* plan to store the aggregate */
    //     $this->transit->store($discussionAggregate);
    //     $plan = $this->transit->getPlan();
    //     $this->assertTrue($plan->contains($discussionAggregate));
    //     $this->assertTrue($plan->contains($discussionAggregate));

    //     /* execute the persistence plan */
    //     $this->transit->persist();

    //     /* did the aggregate components get refreshed with autoinc values? */
    //     $actual = $discussionAggregate->getArrayCopy();
    //     $this->assertSame(21, $actual['thread']['threadId']);
    //     $this->assertSame(13, $actual['thread']['author']['authorId']);
    //     $this->assertSame(101, $actual['replies'][0]['replyId']);
    //     $this->assertSame(14, $actual['replies'][0]['author']['authorId']);
    // }
}
