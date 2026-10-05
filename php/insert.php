<?php
	include( __DIR__ . "/util.php" );

	// 지난번 입력값 : 저장에 실패하면 insertSQL.php 가 세션에 담아 돌려보냄 (다시 입력하지 않도록)
	$old = $_SESSION['old'] ?? array();
	unset( $_SESSION['old'] );

	function oldValue( $old, $name )
	{
		return h( ( isset( $old[$name] ) && is_string( $old[$name] ) ) ? $old[$name] : "" );
	}

	$brands = array();
	$platforms = array();
	$foodTypes = array();
	$categories = array();
	$error = null;

	try
	{
		// MySQL 드라이버 연결 : 입력 칸 자동완성 목록
		$conn = dbConnect();
		$brands = loadBrands( $conn );
		$platforms = loadPlatforms( $conn );
		$foodTypes = dbSelect( $conn, "SELECT DISTINCT food_type FROM brand ORDER BY food_type" );
		$categories = dbSelect( $conn, "SELECT category FROM menu GROUP BY category ORDER BY MIN(menu_id)" );
		mysqli_close( $conn );
	}
	catch ( Throwable $e )
	{
		$error = $e->getMessage();
	}

	writeLog( "insert_view", ( $error !== null ) ? "ERROR " . $error : "" );

	$ruleText = array();
	foreach ( $platforms as $p )
	{
		$ruleText[] = $p['name'] . " " . diffText( $p['default_markup'] );
	}

	$pageTitle = "메뉴 추가";
	$navActive = "insert";
	$flash = takeFlash();
	include( __DIR__ . "/header.php" );
?>
		<h1>메뉴 추가</h1>
		<p class="lead">메뉴와 매장가를 등록합니다. <b>처음 보는 브랜드 이름</b>을 쓰면 브랜드도 함께 추가되므로, 치킨이 아닌 음식도 같은 방법으로 늘릴 수 있습니다.</p>

<?php if ( $error !== null ) { ?>
		<p class="error">DB 오류 : <?php echo h( $error ); ?></p>
<?php } ?>
		<!-- 예제 : ID 를 직접 입력 → 중복 ID 는 DB 오류. 수정 : menu_id 는 AUTO_INCREMENT 로 자동 부여 -->
		<form class="form-card" method="post" action="insertSQL.php">
			<fieldset>
				<legend>브랜드</legend>
				<label>브랜드 이름
					<input type="text" name="brand_name" list="brandList" value="<?php echo oldValue( $old, "brand_name" ); ?>" required maxlength="50" placeholder="예: 교촌치킨">
				</label>
				<label>음식 종류 <small>새 브랜드일 때</small>
					<input type="text" name="food_type" list="foodList" value="<?php echo oldValue( $old, "food_type" ); ?>" maxlength="20" placeholder="예: 치킨, 피자, 버거">
				</label>
				<label class="wide">매장가 출처 <small>새 브랜드일 때 · 공식 홈페이지 메뉴 주소</small>
					<input type="url" name="homepage" value="<?php echo oldValue( $old, "homepage" ); ?>" maxlength="255" placeholder="https://">
				</label>
			</fieldset>

			<fieldset>
				<legend>메뉴</legend>
				<label>분류
					<input type="text" name="category" list="categoryList" value="<?php echo oldValue( $old, "category" ); ?>" required maxlength="30" placeholder="예: 허니시리즈">
				</label>
				<label>메뉴 이름
					<input type="text" name="name" value="<?php echo oldValue( $old, "name" ); ?>" required maxlength="100" placeholder="예: 허니콤보">
				</label>
				<label>매장가(원)
					<input type="text" name="store_price" value="<?php echo oldValue( $old, "store_price" ); ?>" required inputmode="numeric" placeholder="예: 23000">
				</label>
				<label>양·구성 <small>선택</small>
					<input type="text" name="size_info" value="<?php echo oldValue( $old, "size_info" ); ?>" maxlength="100" placeholder="예: 10호 닭 (951~1,050g)">
				</label>
				<label class="wide">이미지 주소 <small>선택</small>
					<input type="url" name="image" value="<?php echo oldValue( $old, "image" ); ?>" maxlength="255" placeholder="https://">
				</label>
			</fieldset>

			<p class="note">저장하면 배달앱 <?php echo count( $platforms ); ?>곳의 기본 배달가(<?php echo h( implode( " · ", $ruleText ) ); ?>)도 함께 만들어집니다. 실제 가격은 <a href="price.php">배달가 입력</a>에서 바꾸세요.</p>
			<button class="btn" type="submit">메뉴 추가</button>

			<!-- 자동완성 목록 -->
			<datalist id="brandList">
<?php foreach ( $brands as $b ) { ?>
				<option value="<?php echo h( $b['name'] ); ?>"><?php echo h( $b['food_type'] ); ?></option>
<?php } ?>
			</datalist>
			<datalist id="foodList">
<?php foreach ( $foodTypes as $f ) { ?>
				<option value="<?php echo h( $f['food_type'] ); ?>"></option>
<?php } ?>
			</datalist>
			<datalist id="categoryList">
<?php foreach ( $categories as $c ) { ?>
				<option value="<?php echo h( $c['category'] ); ?>"></option>
<?php } ?>
			</datalist>
		</form>
<?php include( __DIR__ . "/footer.php" ); ?>
