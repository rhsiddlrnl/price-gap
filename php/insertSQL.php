<?php
	include( __DIR__ . "/util.php" );

	// 주소창으로 직접 들어오면 입력 화면으로
	if ( $_SERVER['REQUEST_METHOD'] !== "POST" )
	{
		redirect( "insert.php" );
	}

	// 이전 페이지에서 전달 받은 메뉴 정보
	$brandName  = param( "brand_name" );
	$foodType   = param( "food_type" );
	$homepage   = param( "homepage" );
	$category   = param( "category" );
	$name       = param( "name" );
	$storePrice = toPrice( param( "store_price" ), 100, 1000000 );
	$sizeInfo   = param( "size_info" );
	$image      = param( "image" );

	try
	{
		// 입력값 확인 (예제 : 확인 없이 바로 INSERT → 빈 값, 중복 ID 는 DB 오류)
		if ( $brandName === "" || $category === "" || $name === "" )
			throw new InvalidArgumentException( "브랜드 이름, 분류, 메뉴 이름은 꼭 입력하세요." );
		if ( tooLong( $brandName, 50 ) || tooLong( $foodType, 20 ) || tooLong( $category, 30 )
			|| tooLong( $name, 100 ) || tooLong( $sizeInfo, 100 ) || tooLong( $homepage, 255 ) || tooLong( $image, 255 ) )
			throw new InvalidArgumentException( "입력 칸의 글자 수가 너무 깁니다." );
		if ( $storePrice === null )
			throw new InvalidArgumentException( "매장가는 100 ~ 1,000,000 사이 숫자로 입력하세요." );
		// 주소는 http(s) 만 허용 : javascript: 주소를 링크에 넣는 XSS 방지
		if ( ( $homepage !== "" && !preg_match( '#^https?://#i', $homepage ) ) || ( $image !== "" && !preg_match( '#^https?://#i', $image ) ) )
			throw new InvalidArgumentException( "주소는 http:// 또는 https:// 로 시작해야 합니다." );

		// MySQL 드라이버 연결
		$conn = dbConnect();

		// 브랜드 추가, 메뉴 추가, 기본 배달가 추가를 한 묶음으로 실행 : 중간에 실패하면 모두 취소 (트랜잭션)
		mysqli_begin_transaction( $conn );

		// 1. 브랜드 : 이미 있으면 그 번호, 없으면 새로 추가
		$found = dbSelect( $conn, "SELECT brand_id FROM brand WHERE name = ?", "s", array( $brandName ) );
		if ( count( $found ) > 0 )
		{
			$brandId = $found[0]['brand_id'];
			$newBrand = false;
		}
		else
		{
			if ( $foodType === "" )
				throw new InvalidArgumentException( "새 브랜드는 음식 종류(예: 치킨, 피자)를 입력하세요." );
			dbExecute( $conn, "INSERT INTO brand ( name, food_type, homepage, checked_on ) VALUES ( ?, ?, ?, ? )",
				"ssss", array( $brandName, $foodType, ( $homepage === "" ) ? null : $homepage, date( 'Y-m-d' ) ) );
			$brandId = mysqli_insert_id( $conn );
			$newBrand = true;
		}

		// 2. 메뉴 (menu_id 는 AUTO_INCREMENT)
		// 예제 : "INSERT INTO book( ... ) VALUES ( '".$id."', '".$title."', ... )" → 제목에 ' 가 있으면 SQL 오류
		dbExecute( $conn, "INSERT INTO menu ( brand_id, category, name, store_price, size_info, image ) VALUES ( ?, ?, ?, ?, ?, ? )",
			"ississ", array( $brandId, $category, $name, $storePrice, ( $sizeInfo === "" ) ? null : $sizeInfo, ( $image === "" ) ? null : $image ) );
		$menuId = mysqli_insert_id( $conn );

		// 3. 배달앱별 기본 배달가 : 매장가 + 배달앱 기본 가산액 (조사 전 임시값)
		$defaults = dbExecute( $conn,
			"INSERT INTO delivery_price ( menu_id, platform_id, price, is_default )
			 SELECT ?, platform_id, ? + default_markup, TRUE FROM platform",
			"ii", array( $menuId, $storePrice ) );

		mysqli_commit( $conn );

		// MySQL 드라이버 연결 해제
		mysqli_close( $conn );

		// 예제 : if( !$result ) 이면 "등록하였습니다" → 성공·실패 메시지가 반대로 나옴
		$message = $brandName . " '" . $name . "' 메뉴를 추가했습니다. (기본 배달가 " . $defaults . "개 생성"
			. ( $newBrand ? ", 새 브랜드 추가" : "" ) . ")";
		setFlash( $message );
		writeLog( "menu_add", "menu=" . $menuId . ", brand=" . $brandName . ", name=" . $name . ", price=" . $storePrice );

		// 방금 추가한 메뉴가 보이는 비교표로 이동
		redirect( "search.php?brand=" . $brandId . "&q=" . urlencode( $name ) );
	}
	catch ( Throwable $e )
	{
		if ( isset( $conn ) ) mysqli_rollback( $conn );

		// 1062 : UNIQUE KEY (brand_id, name) 중복
		$message = ( $e instanceof mysqli_sql_exception && $e->getCode() === 1062 )
			? "이미 등록된 메뉴입니다. (같은 브랜드에 같은 이름)" : $e->getMessage();
		setFlash( "메뉴를 추가하지 못했습니다 : " . $message, "error" );
		writeLog( "menu_add", "ERROR " . $message );

		// 입력한 값을 돌려보내서 다시 입력하지 않도록
		$_SESSION['old'] = array( 'brand_name' => $brandName, 'food_type' => $foodType, 'homepage' => $homepage, 'category' => $category,
			'name' => $name, 'store_price' => param( "store_price" ), 'size_info' => $sizeInfo, 'image' => $image );
		redirect( "insert.php" );
	}
