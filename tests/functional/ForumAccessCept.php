<?php
$I = new FunctionalTester($scenario);
$I->wantTo('test access forum only for admin users');

$forum = $I->createForum(['name' => 'Admin forum', 'slug' => 'Admin_forum']);

// tabela nie ma klucza glownego, dlatego tworzymy przez model poniewaz codeception
// zawsze zaklada ze jest klucz "id"
\Coyote\Forum\Access::create(['forum_id' => $forum->id, 'group_id' => 1]);
$row = $I->grabRecord('forum_access', ['forum_id' => $forum->id]);

$I->assertEquals(1, $row->group_id);

$user = $I->createUser();

$I->amLoggedAs($user);

$I->amOnRoute('forum.home');
$I->seeAuthentication();
$I->see($user->name);
$I->dontSee($forum->name);