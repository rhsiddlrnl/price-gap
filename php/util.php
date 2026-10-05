<?php
	// 모든 페이지 맨 위에서 include 하는 공통 코드 (HTML 출력보다 먼저 실행되어야 함)
	header('Content-Type: text/html; charset=UTF-8');

	// 예제의 date() 는 서버 기본 시간대(UTC)를 써서 로그 시간이 9시간 늦게 찍힘
	date_default_timezone_set('Asia/Seoul');

	// 세션은 HTML 출력 전에 시작해야 쿠키를 보낼 수 있음 (예제 log.php 는 출력 후에 session_start)
	if ( session_status() === PHP_SESSION_NONE )
	{
		session_start();
	}

	include_once( __DIR__ . "/SQLconstants.php" );
	include_once( __DIR__ . "/log.php" );

	// MySQL 연결 요청 및 승인
	// 예제 : mysqli_connect(...) or die("Can't access DB");
	//        PHP 8.1 부터 mysqli 오류는 예외로 발생하므로 or die, if( !$result ) 가 실행되지 않음
	// 수정 : 오류를 예외로 통일하고 각 페이지에서 try-catch 로 처리
	function dbConnect()
	{
		global $mySQL_host, $mySQL_id, $mySQL_password, $mySQL_database;

		mysqli_report( MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT );
		$conn = mysqli_connect( $mySQL_host, $mySQL_id, $mySQL_password, $mySQL_database );
		mysqli_set_charset( $conn, "utf8mb4" );
		return $conn;
	}

	// SQL 실행 요청 : ? 자리에 값만 따로 전달 (SQL Injection 방지)
	// 예제 : "select * from book where title like '%".$message."%';"  → 입력값이 SQL 문법으로 해석됨
	function dbStatement( $conn, $query, $types, $params )
	{
		$stmt = mysqli_prepare( $conn, $query );
		if ( $types !== "" )
		{
			mysqli_stmt_bind_param( $stmt, $types, ...$params );
		}
		mysqli_stmt_execute( $stmt );
		return $stmt;
	}

	// SELECT 실행 결과 : 레코드 배열
	function dbSelect( $conn, $query, $types = "", $params = array() )
	{
		$stmt = dbStatement( $conn, $query, $types, $params );
		$resultSet = mysqli_stmt_get_result( $stmt );
		$rows = mysqli_fetch_all( $resultSet, MYSQLI_ASSOC );
		mysqli_free_result( $resultSet );
		mysqli_stmt_close( $stmt );
		return $rows;
	}

	// INSERT, UPDATE, DELETE 실행 결과 : 바뀐 레코드 수
	// 예제 : DELETE 는 지울 레코드가 없어도 true 를 반환 → "삭제하였습니다" 로 잘못 표시
	function dbExecute( $conn, $query, $types = "", $params = array() )
	{
		$stmt = dbStatement( $conn, $query, $types, $params );
		$affected = mysqli_stmt_affected_rows( $stmt );
		mysqli_stmt_close( $stmt );
		return $affected;
	}

	// 공통 선택 목록
	function loadBrands( $conn )
	{
		return dbSelect( $conn, "SELECT brand_id, name, food_type, homepage, checked_on FROM brand ORDER BY brand_id" );
	}

	function loadPlatforms( $conn )
	{
		return dbSelect( $conn, "SELECT platform_id, name, default_markup FROM platform ORDER BY sort_order, platform_id" );
	}

	// 전달인자 : 값이 없으면 기본값 (예제 : $_POST['message'] → 처음 접속하면 Undefined index 경고)
	function param( $name, $default = "" )
	{
		$value = $_POST[$name] ?? $_GET[$name] ?? $default;
		return is_string( $value ) ? trim( $value ) : $default;
	}

	function intParam( $name, $default = 0 )
	{
		$value = filter_var( param( $name ), FILTER_VALIDATE_INT );
		return ( $value === false ) ? $default : $value;
	}

	// 금액 입력 : "25,000원" 처럼 써도 숫자로 바꿈, 범위를 벗어나면 null
	function toPrice( $text, $min = 0, $max = 1000000 )
	{
		$digits = str_replace( array( ",", " ", "원" ), "", (string)$text );
		if ( !preg_match( '/^\d{1,7}$/', $digits ) )
		{
			return null;
		}
		$price = (int)$digits;
		return ( $price >= $min && $price <= $max ) ? $price : null;
	}

	// 글자 수 확인 (mbstring 확장이 없는 서버에서도 동작하도록 정규식 사용)
	function tooLong( $text, $max )
	{
		return !preg_match( '/^.{0,' . $max . '}$/us', $text );
	}

	// 날짜 입력 : YYYY-MM-DD 가 아니면 null
	function validDate( $text )
	{
		$date = DateTime::createFromFormat( 'Y-m-d', $text );
		return ( $date && $date->format( 'Y-m-d' ) === $text ) ? $text : null;
	}

	// LIKE 검색어 : 사용자가 입력한 %, _ 는 와일드카드가 아닌 글자로 취급
	// 예제 : 검색어가 없으면 "_%" 를 넣어 전체 검색 → 사용자가 _ 를 검색하면 전체가 나옴
	function likePattern( $keyword )
	{
		return "%" . addcslashes( $keyword, "\\%_" ) . "%";
	}

	// HTML 출력용 이스케이프 : <script> 같은 입력이 태그로 실행되지 않도록 (XSS 방지)
	function h( $text )
	{
		return htmlspecialchars( (string)$text, ENT_QUOTES, 'UTF-8' );
	}

	// 23000 → 23,000
	function won( $price )
	{
		return number_format( (int)$price );
	}

	// 차액 : +2,000 / -500 / ±0
	function diffText( $diff )
	{
		$diff = (int)round( $diff );
		if ( $diff > 0 ) return "+" . number_format( $diff );
		if ( $diff < 0 ) return "-" . number_format( -$diff );
		return "±0";
	}

	// 차액 비율 : +8.7%
	function rateText( $rate )
	{
		$rate = round( $rate, 1 );
		return ( ( $rate > 0 ) ? "+" : "" ) . number_format( $rate, 1 ) . "%";
	}

	// 차액 색 : 비쌈(up) / 같음(same) / 쌈(down)
	function diffClass( $diff )
	{
		return ( $diff > 0 ) ? "up" : ( ( $diff < 0 ) ? "down" : "same" );
	}

	// 다음 페이지로 메시지 전달
	// 예제 : hidden form 을 자바스크립트로 자동 submit + " * " 로 시작하면 alert
	//        → 새로고침하면 POST 가 다시 전송되고, 메시지에 ' 가 있으면 자바스크립트 오류
	// 수정 : 세션에 한 번만 보관하고 GET 페이지로 이동 (Post/Redirect/Get)
	function setFlash( $message, $type = "ok" )
	{
		$_SESSION['flash'] = array( 'type' => $type, 'message' => $message );
	}

	function takeFlash()
	{
		$flash = $_SESSION['flash'] ?? null;
		unset( $_SESSION['flash'] );
		return $flash;
	}

	function redirect( $url )
	{
		header( "Location: " . $url );
		exit;
	}

	// 메뉴 이미지 (없거나 불러오지 못하면 메뉴 이름 첫 글자)
	function menuImage( $url, $name )
	{
		$letter = h( preg_replace( '/^(.).*$/us', '$1', $name ) );
		if ( $url === null || $url === "" )
		{
			return "<span class='thumb empty' data-letter='" . $letter . "'></span>";
		}
		return "<span class='thumb' data-letter='" . $letter . "'><img src='" . h( $url ) . "' alt='' loading='lazy' onerror='this.parentNode.classList.add(\"empty\"); this.remove();'></span>";
	}
