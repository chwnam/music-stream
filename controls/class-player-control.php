<?php

namespace music_stream\controls;

use axis_framework\controls\Base_Control;


class Player_Control extends Base_Control {

	const CMD_PLAYER    = '/usr/bin/mplayer -ao alsa:device=bluetooth ';
	const CMD_BT_DEVICE = 'sudo /usr/bin/bt-device ';
	const CMD_BT_AUDIO  = 'sudo /usr/bin/bt-audio ';
	const CMD_SED       = '/bin/sed';
	const DEVICE_NAME   = 'IA160';

	private $pid_path;

	public function __construct( array $args = [] ) {

		parent::__construct( $args );

		$this->pid_path = dirname( MUSIC_STREAM_MAIN_FILE ). '/mplayer.pid';
	}

	private function exec( $command ) {

		$output     = array();
		$return_var = 0;

		exec( $command, $output, $return_var );

		if( $return_var  ) {
			error_log( "command '$command' returned value $return_var" );
		}

		return $output;
	}

	private function process_running( $pid ) {
		$command = "if ps -p $pid > /dev/null; then echo 1; else echo 0; fi";
		return $this->exec( $command );
	}

	public function play( $file ) {

		$this->device_connect();

		if( $this->is_playing() ) {
			$this->stop();
		}

		if( strlen( $file ) > 4  && substr( $file, -4 ) == '.pls' ) {
			$file = '-playlist ' . $file;
		}

		exec( self::CMD_PLAYER . $file . ' < /dev/null > /dev/null 2>&1 & echo $! > ' . $this->pid_path );
	}

	public function stop() {

		$this->exec( "cat {$this->pid_path} | xargs kill; rm -f {$this->pid_path}" );
	}

	public function get_player_pid_list() {

		$command = "ps ax | grep \"" . self::CMD_PLAYER . "\" | grep -v grep | cut -f 2 -d \" \"";
		return $this->exec( $command );
	}

	public function is_playing() {

		$cat = $this->exec( 'cat ' . $this->pid_path );

		if( empty( $cat ) ) {
			return FALSE;
		}

		$pid = $cat[0];
		return is_numeric( $pid ) && $pid > 0;
	}

	public function force_stop() {

		$pid_list = implode( ' ', $this->get_player_pid_list() );
		$this->exec( "kill {$pid_list}" );
	}

	public function device_info() {

		return $this->exec( self::CMD_BT_DEVICE . ' -i ' . self::DEVICE_NAME );
	}

	public function list_devices() {

		return $this->exec( self::CMD_BT_DEVICE . '-l' );
	}

	public function device_check_connection() {

		$command = self::CMD_BT_DEVICE . ' -i ' . self::DEVICE_NAME . ' | ' .
		           self::CMD_SED . ' -rn \'s/ *Connected: ([[:digit:]])/\\1/p\'';
		$output = $this->exec( $command );

		return $output[0];
	}

	public function device_connect() {

		$status = $this->device_check_connection();
		if( $status == 0 ) {
			$command = self::CMD_BT_AUDIO . ' -c ' . self::DEVICE_NAME;
			$this->exec( $command );
		}
		return '1' == $this->device_check_connection();
	}

	public function device_disconnect() {

		$status = $this->device_check_connection();
		if( $status == 1 ) {
			$command = self::CMD_BT_AUDIO . ' -d ' . self::DEVICE_NAME;
			$this->exec( $command );
		}
		return '0' == $this->device_check_connection();
	}
}