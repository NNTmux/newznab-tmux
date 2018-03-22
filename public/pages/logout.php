<?php

use Illuminate\Support\Facades\Auth;

Auth::logout();

redirect('/login');
