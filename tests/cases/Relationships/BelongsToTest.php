<?php

use TestApp\Blog;
use TestApp\User;

class BelongsToTest extends DomainTestCase
{
    public function setUp()
    {
        parent::setUp();
        $this->analogue->registerMapNamespace("TestApp\Maps");
    }

    /** @test */
    public function we_can_store_a_related_entity()
    {
        $blog = $this->factoryCreateUid(Blog::class);
        $user = $this->factoryMakeUid(User::class);
        $mapper = $this->mapper($blog);
        $blog->user = $user;
        $mapper->store($blog);
        $this->assertEquals($user->id, $blog->user_id);
        $this->seeInDatabase('blogs', ['user_id' => $user->id]);
    }

    /** @test */
    public function related_entity_is_hydrated_into_object()
    {
        list($userId, $blogId) = $this->createRelatedRecords();
        $mapper = $this->mapper(User::class);
        $user = $mapper->find($userId);
        $this->assertEquals($blogId, $user->blog->id);
        $this->assertEquals('blog title', $user->blog->title);
    }

    /** @test */
    public function dirty_attributes_on_related_entity_are_updated_on_store()
    {
        list($userId, $blogId) = $this->createRelatedRecords();
        $mapper = $this->mapper(User::class);
        $user = $mapper->find($userId);
        $user->blog->title = 'new title';
        $mapper->store($user);
        $this->seeInDatabase('blogs', [
            'user_id' => $user->id,
            'id'      => $blogId,
            'title'   => 'new title',
        ]);
    }

    /** @test */
    public function foreign_key_is_set_on_null_when_detaching_related_entity()
    {
        list($userId, $blogId) = $this->createRelatedRecords();
        $mapper = $this->mapper(User::class);
        $user = $mapper->find($userId);
        $user->blog = null;
        $mapper->store($user);
        $this->seeInDatabase('blogs', [
            'user_id' => null,
            'id'      => $blogId,
            'title'   => 'blog title',
        ]);
        $user = $mapper->find($userId);
        $this->assertEquals(null, $user->blog);
    }

    protected function createRelatedRecords()
    {
        $userId = $this->insertUser();
        $blogId = $this->rawInsert('blogs', [
            'id'      => $this->randId(),
            'user_id' => $userId,
            'title'   => 'blog title',
        ]);

        return [$userId, $blogId];
    }
}
