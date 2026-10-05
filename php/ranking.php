<?php
	include( __DIR__ . "/util.php" );

	// 전달인자 : 정해진 값만 사용
	// 예제 game/selectSQL.php : "select * from game where title = '".$_POST['title']."' ..." → 전달인자를 SQL 에 그대로 붙임
	$platformId = intParam( "platform" );
	$brandId    = intParam( "brand" );
	$sort       = ( param( "sort" ) === "won" ) ? "won" : "rate";
	$only       = ( param( "only" ) === "1" ) ? 1 : 0;

	$platforms = array();
	$brands = array();
	$summary = array();
	$rows = array();
	$platform = null;
	$error = null;

	try
	{
		// MySQL 드라이버 연결
		$conn = dbConnect();
		$platforms = loadPlatforms( $conn );
		$brands = loadBrands( $conn );

		foreach ( $platforms as $p )
		{
			if ( (int)$p['platform_id'] === $platformId ) $platform = $p;
		}
		if ( $platform === null && count( $platforms ) > 0 ) $platform = $platforms[0];
		$platformId = ( $platform !== null ) ? (int)$platform['platform_id'] : 0;

		// 배달앱별 요약 : GROUP BY 로 평균 차액, 더 비싼 메뉴 수, 직접 조사한 수
		$summary = dbSelect( $conn,
			"SELECT p.platform_id, p.name, COUNT(*) AS menus,
			        AVG( d.price - m.store_price ) AS avg_diff,
			        AVG( ( d.price - m.store_price ) * 100 / m.store_price ) AS avg_rate,
			        MAX( d.price - m.store_price ) AS max_diff,
			        SUM( d.price > m.store_price ) AS higher,
			        SUM( d.is_default = FALSE ) AS researched
			 FROM delivery_price d
			 JOIN menu m ON m.menu_id = d.menu_id
			 JOIN platform p ON p.platform_id = d.platform_id
			 WHERE ( ? = 0 OR m.brand_id = ? ) AND ( ? = 0 OR d.is_default = FALSE )
			 GROUP BY p.platform_id, p.name, p.sort_order
			 ORDER BY p.sort_order, p.platform_id",
			"iii", array( $brandId, $brandId, $only ) );

		// 선택한 배달앱의 메뉴별 차액 순위 (예제 game : order by score desc)
		// ORDER BY 는 ? 로 전달할 수 없으므로 정해진 두 문장 중 하나만 사용
		$orderBy = ( $sort === "won" ) ? "diff DESC, rate DESC" : "rate DESC, diff DESC";
		$rows = dbSelect( $conn,
			"SELECT b.name AS brand, m.menu_id, m.category, m.name, m.store_price,
			        d.price, d.is_default, d.checked_on, d.store_name,
			        d.price - m.store_price AS diff,
			        ( d.price - m.store_price ) * 100 / m.store_price AS rate
			 FROM delivery_price d
			 JOIN menu m ON m.menu_id = d.menu_id
			 JOIN brand b ON b.brand_id = m.brand_id
			 WHERE d.platform_id = ? AND ( ? = 0 OR m.brand_id = ? ) AND ( ? = 0 OR d.is_default = FALSE )
			 ORDER BY " . $orderBy . ", m.menu_id",
			"iiii", array( $platformId, $brandId, $brandId, $only ) );

		// MySQL 드라이버 연결 해제
		mysqli_close( $conn );
	}
	catch ( Throwable $e )
	{
		$error = $e->getMessage();
	}

	// 순위 : 값이 같으면 같은 순위 (예제 game 은 $rank++ 라서 동점도 순위가 다름)
	$rank = 0;
	$prevKey = null;
	$hasDefault = false;
	foreach ( $rows as $i => $row )
	{
		$key = ( $sort === "won" ) ? (string)$row['diff'] : number_format( (float)$row['rate'], 1 );
		if ( $key !== $prevKey )
		{
			$rank = $i + 1;
			$prevKey = $key;
		}
		$rows[$i]['rank'] = $rank;
		if ( $row['is_default'] ) $hasDefault = true;
	}

	// 로그 데이터 기록
	writeLog( "ranking_view", ( $error !== null ) ? "ERROR " . $error
		: "platform=" . $platformId . ", brand=" . $brandId . ", sort=" . $sort . ", only=" . $only . ", rows=" . count( $rows ) );

	// 배달앱 탭 주소 : 지금 고른 조건은 그대로 두고 배달앱만 바꿈
	function rankingUrl( $platformId, $brandId, $sort, $only )
	{
		return "ranking.php?" . http_build_query( array( 'platform' => $platformId, 'brand' => $brandId, 'sort' => $sort, 'only' => $only ) );
	}

	$pageTitle = "차액 랭킹";
	$navActive = "ranking";
	$flash = takeFlash();
	include( __DIR__ . "/header.php" );
?>
		<h1>배달 차액 랭킹</h1>
		<p class="lead">배달앱에서 매장가보다 얼마나 더 받는지 순위로 봅니다. 비율 순은 싼 메뉴와 비싼 메뉴를 공평하게 비교할 때, 금액 순은 실제로 더 내는 돈을 볼 때 씁니다.</p>

		<form class="filters" method="get" action="ranking.php">
			<input type="hidden" name="platform" value="<?php echo $platformId; ?>">
			<select name="brand" aria-label="브랜드">
				<option value="0">모든 브랜드</option>
<?php foreach ( $brands as $b ) { ?>
				<option value="<?php echo (int)$b['brand_id']; ?>"<?php echo ( (int)$b['brand_id'] === $brandId ) ? " selected" : ""; ?>><?php echo h( $b['name'] ); ?></option>
<?php } ?>
			</select>
			<select name="sort" aria-label="정렬">
				<option value="rate"<?php echo ( $sort === "rate" ) ? " selected" : ""; ?>>비율(%) 순</option>
				<option value="won"<?php echo ( $sort === "won" ) ? " selected" : ""; ?>>금액(원) 순</option>
			</select>
			<label class="check"><input type="checkbox" name="only" value="1"<?php echo $only ? " checked" : ""; ?>> 직접 조사한 가격만</label>
			<button class="btn ghost" type="submit">적용</button>
		</form>

<?php if ( $error !== null ) { ?>
		<p class="error">DB 오류 : <?php echo h( $error ); ?></p>
<?php } else { ?>
		<h2 class="section">배달앱별 평균</h2>
<?php if ( count( $summary ) === 0 ) { ?>
		<p class="empty-msg"><?php echo $only ? "직접 조사한 가격이 아직 없습니다. 배달가 입력에서 먼저 입력하세요." : "배달가가 없습니다."; ?></p>
<?php } else { ?>
		<div class="table-wrap">
			<table class="stats">
				<thead>
					<tr>
						<th scope="col">배달앱</th>
						<th scope="col" class="num">메뉴</th>
						<th scope="col" class="num">평균 차액</th>
						<th scope="col" class="num">평균 비율</th>
						<th scope="col" class="num">최대 차액</th>
						<th scope="col" class="num">더 비싼 메뉴</th>
						<th scope="col" class="num">직접 조사</th>
					</tr>
				</thead>
				<tbody>
<?php foreach ( $summary as $s ) { ?>
					<tr<?php echo ( (int)$s['platform_id'] === $platformId ) ? ' class="on"' : ""; ?>>
						<th scope="row"><a href="<?php echo h( rankingUrl( $s['platform_id'], $brandId, $sort, $only ) ); ?>"><?php echo h( $s['name'] ); ?></a></th>
						<td class="num"><?php echo (int)$s['menus']; ?></td>
						<td class="num diff <?php echo diffClass( round( $s['avg_diff'] ) ); ?>"><?php echo diffText( $s['avg_diff'] ); ?>원</td>
						<td class="num"><?php echo rateText( $s['avg_rate'] ); ?></td>
						<td class="num"><?php echo diffText( $s['max_diff'] ); ?>원</td>
						<td class="num"><?php echo (int)$s['higher']; ?></td>
						<td class="num"><?php echo (int)$s['researched']; ?></td>
					</tr>
<?php } ?>
				</tbody>
			</table>
		</div>
<?php } ?>

		<h2 class="section">메뉴별 차액 순위</h2>
		<!-- 배달앱 탭 : 예제 game 의 Tetris / Snake 버튼과 같은 역할 -->
		<nav class="tabs" aria-label="배달앱">
<?php foreach ( $platforms as $p ) { ?>
			<a href="<?php echo h( rankingUrl( $p['platform_id'], $brandId, $sort, $only ) ); ?>"<?php echo ( (int)$p['platform_id'] === $platformId ) ? ' class="on" aria-current="page"' : ""; ?>><?php echo h( $p['name'] ); ?></a>
<?php } ?>
		</nav>
<?php if ( $hasDefault ) { ?>
		<p class="note">회색 줄은 아직 조사하지 않은 기본값입니다. 실제 순위만 보려면 '직접 조사한 가격만'을 체크하세요.</p>
<?php } ?>
<?php if ( count( $rows ) === 0 ) { ?>
		<p class="empty-msg"><?php echo $only ? "이 배달앱에서 직접 조사한 가격이 아직 없습니다." : "배달가가 없습니다."; ?></p>
<?php } else { ?>
		<div class="table-wrap">
			<table class="ranking">
				<thead>
					<tr>
						<th scope="col" class="num">순위</th>
						<th scope="col">메뉴</th>
						<th scope="col" class="num">매장가</th>
						<th scope="col" class="num">배달가</th>
						<th scope="col" class="num">차액</th>
						<th scope="col" class="num">비율</th>
					</tr>
				</thead>
				<tbody>
<?php foreach ( $rows as $r ) { ?>
					<tr class="<?php echo $r['is_default'] ? "is-default" : "is-checked"; ?>">
						<td class="num"><span class="rank<?php echo ( $r['rank'] <= 3 ) ? " top" : ""; ?>"><?php echo $r['rank']; ?></span></td>
						<th scope="row">
							<b><?php echo h( $r['name'] ); ?></b>
							<small><?php echo h( $r['brand'] . " · " . $r['category'] ); ?><?php echo $r['is_default'] ? " · 기본값" : " · 조사 " . h( $r['checked_on'] ?? "" ); ?></small>
						</th>
						<td class="num"><?php echo won( $r['store_price'] ); ?></td>
						<td class="num"><?php echo won( $r['price'] ); ?></td>
						<td class="num diff <?php echo diffClass( $r['diff'] ); ?>"><?php echo diffText( $r['diff'] ); ?></td>
						<td class="num diff <?php echo diffClass( $r['diff'] ); ?>"><?php echo rateText( $r['rate'] ); ?></td>
					</tr>
<?php } ?>
				</tbody>
			</table>
		</div>
<?php } ?>
<?php } ?>
<?php include( __DIR__ . "/footer.php" ); ?>
