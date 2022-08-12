<?php


/*
Various operations on TeamTalk 5 accounts.

© Igor Gorlov, 2022.
*/


declare(strict_types = 1);


// TeamTalk 5 user type.
enum UserType: int {
	case NONE = 0;
	case DEFAULT = 1;
	case ADMIN = 2;
}
