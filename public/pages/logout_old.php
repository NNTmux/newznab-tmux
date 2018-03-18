<?php

use App\Models\User;

User::logout();

header('Location: '.WWW_TOP.'/login');
