<?php

use Illuminate\Database\Seeder;

class ForumpostTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {


        \DB::table('forumpost')->delete();

        \DB::table('forumpost')->insert(array (
            0 =>
            array (
                'id' => 1,
                'forumid' => 1,
                'parentid' => 0,
                'users_id' => 1,
                'subject' => 'Welcome to NNTmux!',
                'message' => 'Feel free to leave a message.',
                'locked' => 0,
                'sticky' => 0,
                'replies' => 0,
                'created_at' => '0000-00-00 00:00:00',
                'updated_at' => '0000-00-00 00:00:00',
            ),
        ));


    }
}