<?php

use Illuminate\Database\Seeder;

class UserRolesTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {


        \DB::table('user_roles')->delete();

        \DB::table('user_roles')->insert(array (
            0 =>
            array (
                'id' => 1,
                'name' => 'User',
                'apirequests' => 10,
                'downloadrequests' => 10,
                'defaultinvites' => 1,
                'isdefault' => 1,
                'canpreview' => 0,
                'hideads' => 0,
                'donation' => 0,
                'addyears' => 0,
            ),
            1 =>
            array (
                'id' => 2,
                'name' => 'Admin',
                'apirequests' => 1000,
                'downloadrequests' => 1000,
                'defaultinvites' => 1000,
                'isdefault' => 0,
                'canpreview' => 1,
                'hideads' => 0,
                'donation' => 0,
                'addyears' => 0,
            ),
            2 =>
            array (
                'id' => 3,
                'name' => 'Disabled',
                'apirequests' => 0,
                'downloadrequests' => 0,
                'defaultinvites' => 0,
                'isdefault' => 0,
                'canpreview' => 0,
                'hideads' => 0,
                'donation' => 0,
                'addyears' => 0,
            ),
            3 =>
            array (
                'id' => 4,
                'name' => 'Moderator',
                'apirequests' => 1000,
                'downloadrequests' => 1000,
                'defaultinvites' => 1000,
                'isdefault' => 0,
                'canpreview' => 1,
                'hideads' => 0,
                'donation' => 0,
                'addyears' => 0,
            ),
            4 =>
            array (
                'id' => 5,
                'name' => 'Friend',
                'apirequests' => 100,
                'downloadrequests' => 100,
                'defaultinvites' => 5,
                'isdefault' => 0,
                'canpreview' => 1,
                'hideads' => 0,
                'donation' => 0,
                'addyears' => 0,
            ),
        ));


    }
}