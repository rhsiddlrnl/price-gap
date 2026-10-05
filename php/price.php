<?php
	include( __DIR__ . "/util.php" );

	// 배달가 입력 : 브랜드 하나 x 배달앱 하나의 메뉴 가격을 한 화면에서 입력 (GET : brand, platform)
	// 예제에는 없던 UPDATE 기능 → 검색(SELECT) · 추가(INSERT) · 수정(UPDATE) · 삭제(DELETE) 를 모두 갖춤
	$brandId    = intParam( "brand" );
	$platformId = intParam( "platform" );

	// 지난번 조사 정보 : 같은 매장을 이어서 조사할 때 다시 입력하지 않도록 세션에 기억
	$research    = $_SESSION['research'] ?? array();
	$storeName   = $research['store_name'] ?? "";
	$checkedOn   = $research['checked_on'] ?? date( 'Y-m-d' );
	$deliveryFee = $research['delivery_fee'] ?? "";

	$brands = array();
	$platforms = array();
	$menus = array();
	$brand = null;
	$platform = null;
	$error = null;

	try
	{
		// MySQL 드라이버 연결
		$conn = dbConnect();
		$brands = loadBrands( $conn );
		$platforms = loadPlatforms( $conn );

		// 고르지 않았거나 없는 번호면 첫 번째 브랜드, 첫 번째 배달앱
		foreach ( $brands as $b )
		{
			if ( (int)$b['brand_id'] === $brandId ) $brand = $b;
		}
		foreach ( $platforms as $p )
		{
			if ( (int)$p['platform_id'] === $platformId ) $platform = $p;
		}
		if ( $brand === null && count( $brands ) > 0 ) $brand = $brands[0];
		if ( $platform === null && count( $platforms ) > 0 ) $platform = $platforms[0];

		if ( $brand !== null && $platform !== null )
		{
			$brandId = (int)$brand['brand_id'];
			$platformId = (int)$platform['platform_id'];

			// 메뉴별 현재 배달가 : 배달가가 없는 메뉴도 보이도록 LEFT JOIN (배달앱 조건은 ON 에)
			$menus = dbSelect( $conn,
				"SELECT m.menu_id, m.category, m.name, m.store_price, m.size_info,
				        d.price, d.delivery_fee, d.is_default, d.store_name, d.checked_on
				 FROM menu m
				 LEFT JOIN delivery_price d ON d.menu_id = m.menu_id AND d.platform_id = ?
				 WHERE m.brand_id = ?
				 ORDER BY m.menu_id",
				"ii", array( $platformId, $brandId ) );
		}

		// MySQL 드라이버 연결 해제
		mysqli_close( $conn );
	}
	catch ( Throwable $e )
	{
		$error = $e->getMessage();
	}

	// 로그 데이터 기록
	writeLog( "price_view", ( $error !== null ) ? "ERROR " . $error
		: "brand=" . $brandId . ", platform=" . $platformId . ", menus=" . count( $menus ) );

	$researched = 0;
	foreach ( $menus as $m )
	{
		if ( $m['price'] !== null && !$m['is_default'] ) $researched++;
	}

	$pageTitle = "배달가 입력";
	$navActive = "price";
	$flash = takeFlash();
	include( __DIR__ . "/header.php" );
?>
		<h1>배달가 입력</h1>
		<p class="lead">배달앱에서 확인한 가격을 적어 주세요. 값을 고친 메뉴는 자동으로 체크되고, <b>체크한 메뉴만</b> '직접 조사'로 저장됩니다.</p>

		<!-- 1. 브랜드, 배달앱 고르기 (GET) -->
		<form class="filters" method="get" action="price.php">
			<select name="brand" aria-label="브랜드">
<?php foreach ( $brands as $b ) { ?>
				<option value="<?php echo (int)$b['brand_id']; ?>"<?php echo ( (int)$b['brand_id'] === $brandId ) ? " selected" : ""; ?>><?php echo h( $b['name'] ); ?></option>
<?php } ?>
			</select>
			<select name="platform" aria-label="배달앱">
<?php foreach ( $platforms as $p ) { ?>
				<option value="<?php echo (int)$p['platform_id']; ?>"<?php echo ( (int)$p['platform_id'] === $platformId ) ? " selected" : ""; ?>><?php echo h( $p['name'] ); ?></option>
<?php } ?>
			</select>
			<button class="btn ghost" type="submit">불러오기</button>
		</form>

<?php if ( $error !== null ) { ?>
		<p class="error">DB 오류 : <?php echo h( $error ); ?></p>
<?php } else if ( $brand === null || $platform === null ) { ?>
		<p class="empty-msg">브랜드나 배달앱이 없습니다. SQL.txt 의 데이터를 먼저 입력하세요.</p>
<?php } else { ?>
		<!-- 2. 가격 입력 (POST → priceSQL.php) -->
		<form id="priceForm" method="post" action="priceSQL.php">
			<input type="hidden" name="brand" value="<?php echo $brandId; ?>">
			<input type="hidden" name="platform" value="<?php echo $platformId; ?>">

			<fieldset class="research">
				<legend><?php echo h( $brand['name'] ); ?> · <?php echo h( $platform['name'] ); ?> 조사 정보 <small>체크한 메뉴에 함께 저장</small></legend>
				<label>조사 매장
					<input type="text" name="store_name" value="<?php echo h( $storeName ); ?>" maxlength="50" placeholder="예: <?php echo h( $brand['name'] ); ?> OO점">
				</label>
				<label>조사일
					<input type="date" name="checked_on" value="<?php echo h( $checkedOn ); ?>" required>
				</label>
				<label>배달팁(원)
					<input type="text" name="delivery_fee" value="<?php echo h( $deliveryFee ); ?>" inputmode="numeric" placeholder="모르면 비워 두기">
				</label>
				<button class="btn" type="submit">체크한 메뉴 저장</button>
			</fieldset>

			<p class="count">
				메뉴 <b><?php echo count( $menus ); ?></b>개 · 직접 조사 <b><?php echo $researched; ?></b>개 ·
				기본값 규칙 : 매장가 <?php echo diffText( $platform['default_markup'] ); ?>원
			</p>

			<div class="table-wrap">
				<table class="price-table">
					<thead>
						<tr>
							<th scope="col">저장</th>
							<th scope="col">메뉴</th>
							<th scope="col" class="num">매장가</th>
							<th scope="col" class="num">지금 배달가</th>
							<th scope="col">새 배달가</th>
							<th scope="col" class="num">차액</th>
							<th scope="col"><span class="sr-only">되돌리기</span></th>
						</tr>
					</thead>
					<tbody>
<?php foreach ( $menus as $m ) {
	$current = $m['price'];
	$suggest = ( $current !== null ) ? $current : $m['store_price'] + $platform['default_markup'];
	$diff = $suggest - $m['store_price']; ?>
						<tr id="menu-<?php echo $m['menu_id']; ?>">
							<td class="pick"><input type="checkbox" name="menu_ids[]" value="<?php echo $m['menu_id']; ?>" aria-label="<?php echo h( $m['name'] ); ?> 저장"></td>
							<th scope="row">
								<b><?php echo h( $m['name'] ); ?></b>
								<small><?php echo h( $m['category'] ); ?><?php echo ( $m['size_info'] !== null && $m['size_info'] !== "" ) ? " · " . h( $m['size_info'] ) : ""; ?></small>
							</th>
							<td class="num"><?php echo won( $m['store_price'] ); ?></td>
							<td class="num">
<?php if ( $current === null ) { ?>
								<span class="badge none">없음</span>
<?php } else if ( $m['is_default'] ) { ?>
								<span class="muted"><?php echo won( $current ); ?></span> <span class="badge">기본값</span>
<?php } else { ?>
								<?php echo won( $current ); ?> <span class="badge checked" title="<?php echo h( trim( $m['store_name'] . " " . ( $m['delivery_fee'] !== null ? "배달팁 " . won( $m['delivery_fee'] ) . "원" : "" ) ) ); ?>">조사 <?php echo h( $m['checked_on'] ?? "" ); ?></span>
<?php } ?>
							</td>
							<td><input class="price-input" type="text" inputmode="numeric" name="prices[<?php echo $m['menu_id']; ?>]" value="<?php echo $suggest; ?>" data-store="<?php echo (int)$m['store_price']; ?>" aria-label="<?php echo h( $m['name'] ); ?> 새 배달가"></td>
							<td class="num diff <?php echo diffClass( $diff ); ?>"><?php echo diffText( $diff ); ?></td>
							<td>
<?php if ( $current !== null && !$m['is_default'] ) { ?>
								<button class="btn small ghost" type="submit" form="resetForm" name="menu_id" value="<?php echo $m['menu_id']; ?>">기본값으로</button>
<?php } ?>
							</td>
						</tr>
<?php } ?>
					</tbody>
				</table>
			</div>
			<p class="actions"><button class="btn" type="submit">체크한 메뉴 저장</button></p>
		</form>

		<!-- 기본값으로 되돌리기 : 표 안의 버튼이 form="resetForm" 으로 이 폼을 제출 -->
		<form id="resetForm" method="post" action="priceSQL.php">
			<input type="hidden" name="mode" value="reset">
			<input type="hidden" name="brand" value="<?php echo $brandId; ?>">
			<input type="hidden" name="platform" value="<?php echo $platformId; ?>">
		</form>

		<script>
			// 값을 고치면 그 메뉴의 '저장' 칸을 체크하고 차액을 다시 계산
			document.querySelectorAll( ".price-input" ).forEach( function ( input ) {
				input.addEventListener( "input", function () {
					var row = input.closest( "tr" );
					var cell = row.querySelector( ".diff" );
					var price = parseInt( input.value.replace( /[^0-9]/g, "" ), 10 );
					var diff = price - parseInt( input.dataset.store, 10 );

					row.querySelector( "input[type=checkbox]" ).checked = true;
					if ( isNaN( price ) ) {
						cell.textContent = "?";
						cell.className = "num diff";
						return;
					}
					cell.textContent = ( diff > 0 ? "+" : ( diff < 0 ? "-" : "±" ) ) + Math.abs( diff ).toLocaleString( "ko-KR" );
					cell.className = "num diff " + ( diff > 0 ? "up" : ( diff < 0 ? "down" : "same" ) );
				} );
			} );
		</script>
<?php } ?>
<?php include( __DIR__ . "/footer.php" ); ?>
