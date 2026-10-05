<?php
	// 로그 데이터 기록
	// 예제 : writeLog() 를 만들어 두었지만 어느 페이지에서도 호출하지 않아서 log.txt 가 쌓이지 않음
	// 수정 : 모든 페이지에서 호출하고, 무슨 행동인지 알 수 있도록 Action 항목 추가
	//        search, menu_add, menu_delete, price_save, price_reset, ranking_view ...
	function writeLog( $action, $message )
	{
		// 로그 파일 : 이 파일과 같은 폴더의 log.txt (예제처럼 서버 경로 /var/www/html/... 를 직접 적지 않아도 됨)
		$logFileName = __DIR__ . "/log.txt";
		$logFile = @fopen( $logFileName, "a" );
		if ( $logFile )
		{
			// 로그 데이터 출력
			fwrite( $logFile, "\nTime:\t" . date( 'Y-m-d H:i:s' )			// 접속 시간
				. "\tSessionID:\t" . session_id()				// 접속 ID
				. "\tAction:\t" . $action					// 행동 종류
				. "\tURI:\t" . logText( $_SERVER['PHP_SELF'] ?? "" )		// 현재 페이지
				. "\tPrevious:\t" . logText( $_SERVER['HTTP_REFERER'] ?? "" )	// 접속 경로(이전페이지), 직접 접속하면 없음
				. "\tBrowser:\t" . logText( $_SERVER['HTTP_USER_AGENT'] ?? "" )	// 접속 브라우저
				. "\tMessage:\t" . logText( $message ) );
			fclose( $logFile );
		}
		else
		{
			// 예제는 화면에 오류를 echo → 페이지가 깨짐. 로그를 못 남겨도 페이지는 정상 표시하고 서버 오류 로그에만 기록
			error_log( "[log.php] 로그 파일을 열 수 없습니다 (쓰기 권한 확인) : " . $logFileName );
		}
	}

	// 탭, 줄바꿈이 들어가면 로그 한 줄이 여러 줄로 깨지므로 공백으로 바꿈
	function logText( $text )
	{
		return preg_replace( '/[\t\r\n]+/', ' ', (string)$text );
	}
