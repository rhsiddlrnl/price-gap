<?php
	include( __DIR__ . "/util.php" );

	// 주소창으로 직접 들어오면 입력 화면으로
	if ( $_SERVER['REQUEST_METHOD'] !== "POST" )
	{
		redirect( "price.php" );
	}

	// 이전 페이지에서 전달 받은 값
	$mode       = param( "mode", "save" );
	$brandId    = intParam( "brand" );
	$platformId = intParam( "platform" );
	$back = "price.php?brand=" . $brandId . "&platform=" . $platformId;
	$logMessage = "";

	try
	{
		// MySQL 드라이버 연결
		$conn = dbConnect();

		if ( $mode === "reset" )
		{
			// 기본값으로 되돌리기 : 배달가 = 매장가 + 배달앱 기본 가산액, 조사 정보는 지움
			$menuId = intParam( "menu_id" );
			$count = dbExecute( $conn,
				"UPDATE delivery_price d
				 JOIN menu m ON m.menu_id = d.menu_id
				 JOIN platform p ON p.platform_id = d.platform_id
				 SET d.price = m.store_price + p.default_markup, d.delivery_fee = NULL,
				     d.is_default = TRUE, d.store_name = NULL, d.checked_on = NULL
				 WHERE d.menu_id = ? AND d.platform_id = ?",
				"ii", array( $menuId, $platformId ) );

			setFlash( ( $count > 0 ) ? "기본값으로 되돌렸습니다." : "되돌릴 배달가가 없습니다.", ( $count > 0 ) ? "ok" : "error" );
			$back .= "#menu-" . $menuId;
			$logMessage = "menu=" . $menuId . ", platform=" . $platformId . ", count=" . $count;
		}
		else
		{
			// 조사 정보
			$storeName   = param( "store_name" );
			$checkedOn   = validDate( param( "checked_on" ) );
			$feeText     = param( "delivery_fee" );
			$deliveryFee = ( $feeText === "" ) ? null : toPrice( $feeText, 0, 100000 );
			$menuIds     = $_POST['menu_ids'] ?? array();
			$prices      = $_POST['prices'] ?? array();

			// 다음에 이어서 입력할 때 다시 쓰도록 기억
			$_SESSION['research'] = array( 'store_name' => $storeName, 'checked_on' => $checkedOn ?? date( 'Y-m-d' ), 'delivery_fee' => $feeText );

			// 입력값 확인 (예제 : 확인 없이 바로 SQL 실행 → 잘못된 값은 DB 오류 메시지가 그대로 노출)
			if ( tooLong( $storeName, 50 ) )
				throw new InvalidArgumentException( "조사 매장은 50자까지 입력할 수 있습니다." );
			if ( $checkedOn === null )
				throw new InvalidArgumentException( "조사일을 YYYY-MM-DD 형식으로 입력하세요." );
			if ( $feeText !== "" && $deliveryFee === null )
				throw new InvalidArgumentException( "배달팁은 0 ~ 100,000 사이 숫자로 입력하세요." );
			if ( !is_array( $menuIds ) || !is_array( $prices ) || count( $menuIds ) === 0 )
				throw new InvalidArgumentException( "저장할 메뉴를 체크하세요. (가격을 고치면 자동으로 체크됩니다)" );

			// 여러 메뉴를 한 번에 저장 : 중간에 오류가 나면 모두 취소 (트랜잭션)
			mysqli_begin_transaction( $conn );
			$saved = 0;
			$skipped = 0;
			foreach ( $menuIds as $menuIdText )
			{
				$menuId = filter_var( $menuIdText, FILTER_VALIDATE_INT );
				$price = ( $menuId === false ) ? null : toPrice( $prices[$menuId] ?? "", 1, 1000000 );
				if ( $price === null )
				{
					$skipped++;
					continue;
				}

				// 배달가가 이미 있으면 UPDATE, 없으면 INSERT (PRIMARY KEY = menu_id + platform_id)
				dbExecute( $conn,
					"INSERT INTO delivery_price ( menu_id, platform_id, price, delivery_fee, is_default, store_name, checked_on )
					 VALUES ( ?, ?, ?, ?, FALSE, ?, ? )
					 ON DUPLICATE KEY UPDATE price = VALUES( price ), delivery_fee = VALUES( delivery_fee ),
					     is_default = FALSE, store_name = VALUES( store_name ), checked_on = VALUES( checked_on )",
					"iiiiss", array( $menuId, $platformId, $price, $deliveryFee, ( $storeName === "" ) ? null : $storeName, $checkedOn ) );
				$saved++;
			}
			mysqli_commit( $conn );

			$message = "배달가 " . $saved . "개를 저장했습니다.";
			if ( $skipped > 0 ) $message .= " (가격이 숫자가 아닌 " . $skipped . "개는 건너뜀)";
			setFlash( $message, ( $saved > 0 ) ? "ok" : "error" );
			$logMessage = "platform=" . $platformId . ", saved=" . $saved . ", skipped=" . $skipped . ", store=" . $storeName . ", date=" . $checkedOn;
		}

		// MySQL 드라이버 연결 해제
		mysqli_close( $conn );
	}
	catch ( Throwable $e )
	{
		// 커밋 전에 오류가 나면 이번에 저장한 것도 모두 취소
		if ( isset( $conn ) ) mysqli_rollback( $conn );
		setFlash( "저장하지 못했습니다 : " . $e->getMessage(), "error" );
		$logMessage = "ERROR " . $e->getMessage();
	}

	// 로그 데이터 기록 후 입력 화면으로 이동 (Post/Redirect/Get : 새로고침해도 다시 저장되지 않음)
	writeLog( ( $mode === "reset" ) ? "price_reset" : "price_save", $logMessage );
	redirect( $back );
