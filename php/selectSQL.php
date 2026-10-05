<?php
	// 가격 비교표 데이터 조회 : search.php, delete.php 에서 include
	// 예제 selectSQL.php : SQL 실행과 HTML 출력(echo)이 한 파일에 섞여 있음
	// 수정 : 이 파일은 SQL 실행 결과를 배열에 담기만 하고, 화면 출력은 include 한 페이지가 담당

	// 검색 조건 : include 한 페이지에서 정하지 않았으면 전달인자(GET) 사용
	$keyword   = $keyword   ?? param( "q" );
	$foodType  = $foodType  ?? param( "food" );
	$brandId   = $brandId   ?? intParam( "brand" );
	$category  = $category  ?? param( "category" );
	$logAction = $logAction ?? "search";

	$brands = array();		// 브랜드 선택 목록
	$platforms = array();		// 배달앱 = 비교표의 열
	$foodTypes = array();		// 음식 종류 선택 목록
	$categories = array();		// 분류 선택 목록
	$groups = array();		// 비교표 : "브랜드 · 분류" 별 메뉴 목록
	$stats = array( 'menus' => 0, 'cells' => 0, 'higher' => 0, 'researched' => 0, 'diffSum' => 0, 'rateSum' => 0 );
	$error = null;

	try
	{
		// MySQL 드라이버 연결
		$conn = dbConnect();

		// 검색 조건 선택 목록
		$brands = loadBrands( $conn );
		$platforms = loadPlatforms( $conn );
		$foodTypes = dbSelect( $conn, "SELECT DISTINCT food_type FROM brand ORDER BY food_type" );
		$categories = dbSelect( $conn,
			"SELECT category, MIN(menu_id) AS first_id FROM menu WHERE ( ? = 0 OR brand_id = ? ) GROUP BY category ORDER BY first_id",
			"ii", array( $brandId, $brandId ) );

		// MySQL 메뉴 검색 실행 : 메뉴 + 브랜드 + 배달가 3개 테이블 JOIN (메뉴 1개당 배달앱 수만큼 레코드)
		$like = likePattern( $keyword );
		$query = "SELECT b.brand_id, b.name AS brand, m.menu_id, m.category, m.name, m.store_price, m.size_info, m.image,
		                 d.platform_id, d.price, d.delivery_fee, d.is_default, d.store_name, d.checked_on
		          FROM menu m
		          JOIN brand b ON b.brand_id = m.brand_id
		          LEFT JOIN delivery_price d ON d.menu_id = m.menu_id
		          WHERE ( m.name LIKE ? OR m.category LIKE ? OR b.name LIKE ? )
		            AND ( ? = '' OR b.food_type = ? )
		            AND ( ? = 0 OR b.brand_id = ? )
		            AND ( ? = '' OR m.category = ? )
		          ORDER BY b.brand_id, m.menu_id";
		$rows = dbSelect( $conn, $query, "sssssiiss",
			array( $like, $like, $like, $foodType, $foodType, $brandId, $brandId, $category, $category ) );

		// MySQL 드라이버 연결 해제
		mysqli_close( $conn );

		// 레코드를 메뉴 한 줄로 묶기 : $groups[ 브랜드|분류 ]['menus'][ menu_id ]['prices'][ platform_id ]
		foreach ( $rows as $row )
		{
			$groupKey = $row['brand_id'] . "|" . $row['category'];
			if ( !isset( $groups[$groupKey] ) )
			{
				$groups[$groupKey] = array( 'brand' => $row['brand'], 'category' => $row['category'], 'menus' => array() );
			}

			$menuId = $row['menu_id'];
			if ( !isset( $groups[$groupKey]['menus'][$menuId] ) )
			{
				$groups[$groupKey]['menus'][$menuId] = array(
					'menu_id' => $menuId, 'brand_id' => $row['brand_id'], 'brand' => $row['brand'],
					'name' => $row['name'], 'store_price' => $row['store_price'],
					'size_info' => $row['size_info'], 'image' => $row['image'], 'prices' => array() );
				$stats['menus']++;
			}

			// 배달가가 없는 메뉴 (LEFT JOIN 결과 NULL) 는 빈 칸으로 표시
			if ( $row['platform_id'] !== null )
			{
				$groups[$groupKey]['menus'][$menuId]['prices'][$row['platform_id']] = $row;

				$diff = $row['price'] - $row['store_price'];
				$stats['cells']++;
				$stats['diffSum'] += $diff;
				$stats['rateSum'] += $diff * 100 / $row['store_price'];
				if ( $diff > 0 ) $stats['higher']++;
				if ( !$row['is_default'] ) $stats['researched']++;
			}
		}
	}
	catch ( Throwable $e )
	{
		// 예제 : 연결 실패는 die 로 멈추고, 검색 실패는 화면에 알리지 않음 → 화면과 로그에 오류 표시
		$error = $e->getMessage();
	}

	// 로그 데이터 기록
	writeLog( $logAction, ( $error !== null ) ? "ERROR " . $error
		: "keyword=" . $keyword . ", food=" . $foodType . ", brand=" . $brandId . ", category=" . $category . ", menus=" . $stats['menus'] );
