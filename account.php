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

// Encapsulates TeamTalk 5 user information.
class UserInfo {

	// TeamTalk 5 user rights.
	const RIGHT_NONE = 0x00000000;
	const RIGHT_MULTILOGIN = 0x00000001;
	const RIGHT_VIEW_ALL_USERS = 0x00000002;
	const RIGHT_CREATE_TEMPORARY_CHANNEL = 0x00000004;
	const RIGHT_MODIFY_CHANNELS = 0x00000008;
	const RIGHT_TEXT_MESSAGE_BROADCAST = 0x00000010;
	const RIGHT_KICK_USERS = 0x00000020;
	const RIGHT_BAN_USERS = 0x00000040;
	const RIGHT_MOVE_USERS = 0x00000080;
	const RIGHT_OPERATOR_ENABLE = 0x00000100;
	const RIGHT_UPLOAD_FILES = 0x00000200;
	const RIGHT_DOWNLOAD_FILES = 0x00000400;
	const RIGHT_UPDATE_SERVER_PROPERTIES = 0x00000800;
	const RIGHT_TRANSMIT_VOICE = 0x00001000;
	const RIGHT_TRANSMIT_VIDEO_CAPTURE = 0x00002000;
	const RIGHT_TRANSMIT_DESKTOP = 0x00004000;
	const RIGHT_TRANSMIT_DESKTOP_INPUT = 0x00008000;
	const RIGHT_TRANSMIT_MEDIA_FILE_AUDIO = 0x00010000;
	const RIGHT_TRANSMIT_MEDIA_FILE_VIDEO = 0x00020000;
	const RIGHT_TRANSMIT_MEDIA_FILE = self::RIGHT_TRANSMIT_MEDIA_FILE_AUDIO | self::RIGHT_TRANSMIT_MEDIA_FILE_VIDEO;
	const RIGHT_LOCKED_NICKNAME = 0x00040000;
	const RIGHT_LOCKED_STATUS = 0x00080000;
	const RIGHT_RECORD_VOICE = 0x00100000;
	const RIGHT_VIEW_HIDDEN_CHANNELS = 0x00200000;
	const RIGHT_DEFAULT = self::RIGHT_MULTILOGIN | self::RIGHT_VIEW_ALL_USERS | self::RIGHT_CREATE_TEMPORARY_CHANNEL |
		self::RIGHT_UPLOAD_FILES | self::RIGHT_DOWNLOAD_FILES | self::RIGHT_TRANSMIT_VOICE |
		self::RIGHT_TRANSMIT_VIDEO_CAPTURE | self::RIGHT_TRANSMIT_DESKTOP | self::RIGHT_TRANSMIT_DESKTOP_INPUT |
		self::RIGHT_TRANSMIT_MEDIA_FILE;
	const RIGHT_ADMIN = 0x001fffff; // All flags in one.

	// Throws InvalidArgumentException if one or more of the passed values do not comply to the requirements.
	public function __construct(
		Validator $validator,
		public readonly ServerInfo $server,
		public readonly string $username,
		public readonly string $password,
		public readonly string $nickname = "",
		public readonly UserType $type = UserType::DEFAULT,
		public readonly int $rights = self::RIGHT_DEFAULT
	) {
		$error = false;
		$errorMessage = "The following user properties are invalid:\n";
		if(!$validator->isValidUsername($username)) {
			$error = true;
			$errorMessage .= "\tUsername\n";
		}
		if(!$validator->isValidPassword($password)) {
			$error = true;
			$errorMessage .= "\tPassword\n";
		}
		if(!$validator->isValidNickname($nickname)) {
			$error = true;
			$errorMessage .= "\tNickname\n";
		}
		if($error) {
			throw new InvalidArgumentException($errorMessage);
		}
	}

}
