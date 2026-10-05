<?php
	include( __DIR__ . "/util.php" );

	// 주소창으로 직접 들어오면 삭제 화면으로
	if ( $_SERVER['REQUEST_METHOD'] !== "POST" )
	{
		redirect( "delete.php" );
	}

	// 이전 페이지에서 전달 받은 메뉴 번호 : 숫자만 허용
	$menuId = intParam( "menu_id" );
	$count = 0;

	try
	{
		if ( $menuId <= 0 )
			throw new InvalidArgumentException( "삭제할 메뉴를 고르세요." );

		// MySQL 드라이버 연결
		$conn = dbConnect();

		// 결과 메시지에 쓸 메뉴 이름
		$found = dbSelect( $conn,
			"SELECT m.name, m.brand_id, b.name AS brand FROM menu m JOIN brand b ON b.brand_id = m.brand_id WHERE m.menu_id = ?",
			"i", array( $menuId ) );

		// MySQL 메뉴 삭제 실행 : 배달가(delivery_price)는 FOREIGN KEY ... ON DELETE CASCADE 로 함께 삭제
		// 예제 : "delete from book where id = '".$id."';" → 1' or '1'='1 을 입력하면 모든 책이 삭제됨
		$count = dbExecute( $conn, "DELETE FROM menu WHERE menu_id = ?", "i", array( $menuId ) );

		if ( $count > 0 )
		{
			// 메뉴가 하나도 남지 않은 브랜드도 정리
			$brandId = $found[0]['brand_id'];
			dbExecute( $conn, "DELETE FROM brand WHERE brand_id = ? AND NOT EXISTS ( SELECT 1 FROM menu WHERE brand_id = ? )",
				"ii", array( $brandId, $brandId ) );
			$message = $found[0]['brand'] . " '" . $found[0]['name'] . "' 메뉴를 삭제했습니다.";
		}
		else
		{
			// 예제 : DELETE 는 지운 레코드가 0개여도 성공(true) → "삭제하였습니다" 로 잘못 표시
			$message = "메뉴 번호 " . $menuId . " 를 찾을 수 없습니다. (이미 삭제되었을 수 있음)";
		}

		// MySQL 드라이버 연결 해제
		mysqli_close( $conn );
	}
	catch ( Throwable $e )
	{
		$message = "삭제하지 못했습니다 : " . $e->getMessage();
	}

	setFlash( $message, ( $count > 0 ) ? "ok" : "error" );
	writeLog( "menu_delete", "menu=" . $menuId . ", count=" . $count . ", " . $message );
	redirect( "delete.php" );
