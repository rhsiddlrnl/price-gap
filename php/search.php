<?php
	include( __DIR__ . "/util.php" );

	// 메뉴 검색 실행 (검색 조건 : GET 전달인자 q, food, brand, category)
	include( __DIR__ . "/selectSQL.php" );

	$filtered = ( $keyword !== "" || $foodType !== "" || $brandId !== 0 || $category !== "" );
	$columnCount = 2 + count( $platforms );
	$brandCount = count( array_unique( array_column( $groups, 'brand' ) ) );

	// 기본값 규칙 안내 : 배달의민족 +2,000 · ... · 자사앱 ±0
	$ruleText = array();
	foreach ( $platforms as $p )
	{
		$ruleText[] = $p['name'] . " " . diffText( $p['default_markup'] );
	}

	$pageTitle = "가격 비교";
	$navActive = "search";
	$flash = takeFlash();
	include( __DIR__ . "/header.php" );
?>
		<section class="hero">
			<h1>배달로 시키면 얼마나 더 낼까?</h1>
			<p>같은 메뉴의 <b>매장가</b>(공식 홈페이지 권장소비자가격)와 <b>배달앱 가격</b>을 나란히 비교합니다.</p>
		</section>

		<!-- 검색 조건 : GET 으로 전달 (예제는 POST → 새로고침하면 재전송 경고, 검색 결과 주소를 공유할 수 없음) -->
		<form class="filters" method="get" action="search.php">
			<input type="search" name="q" value="<?php echo h( $keyword ); ?>" placeholder="메뉴·분류·브랜드 (예: 허니, 순살)" aria-label="검색어">
			<select name="food" aria-label="음식 종류">
				<option value="">모든 음식</option>
<?php foreach ( $foodTypes as $f ) { ?>
				<option value="<?php echo h( $f['food_type'] ); ?>"<?php echo ( $f['food_type'] === $foodType ) ? " selected" : ""; ?>><?php echo h( $f['food_type'] ); ?></option>
<?php } ?>
			</select>
			<select name="brand" aria-label="브랜드">
				<option value="0">모든 브랜드</option>
<?php foreach ( $brands as $b ) { ?>
				<option value="<?php echo (int)$b['brand_id']; ?>"<?php echo ( (int)$b['brand_id'] === $brandId ) ? " selected" : ""; ?>><?php echo h( $b['name'] ); ?></option>
<?php } ?>
			</select>
			<select name="category" aria-label="분류">
				<option value="">모든 분류</option>
<?php foreach ( $categories as $c ) { ?>
				<option value="<?php echo h( $c['category'] ); ?>"<?php echo ( $c['category'] === $category ) ? " selected" : ""; ?>><?php echo h( $c['category'] ); ?></option>
<?php } ?>
			</select>
			<button class="btn" type="submit">검색</button>
<?php if ( $filtered ) { ?>
			<a class="btn ghost" href="search.php">초기화</a>
<?php } ?>
		</form>

<?php if ( $error !== null ) { ?>
		<p class="error">DB 오류 : <?php echo h( $error ); ?></p>
<?php } else if ( $stats['menus'] === 0 ) { ?>
		<p class="empty-msg">검색 결과가 없습니다.</p>
<?php } else { ?>
		<!-- 요약 -->
		<div class="summary">
			<div>
				<span>메뉴</span>
				<b><?php echo $stats['menus']; ?>개</b>
				<small>브랜드 <?php echo $brandCount; ?>곳</small>
			</div>
			<div>
				<span>배달가 평균 차액</span>
<?php if ( $stats['cells'] > 0 ) { $avgDiff = $stats['diffSum'] / $stats['cells']; ?>
				<b class="<?php echo diffClass( round( $avgDiff ) ); ?>"><?php echo diffText( $avgDiff ); ?>원</b>
				<small>매장가보다 <?php echo rateText( $stats['rateSum'] / $stats['cells'] ); ?></small>
<?php } else { ?>
				<b>-</b>
<?php } ?>
			</div>
			<div>
				<span>배달이 더 비싼 칸</span>
				<b><?php echo $stats['higher']; ?> <small>/ <?php echo $stats['cells']; ?></small></b>
				<small>메뉴 x 배달앱</small>
			</div>
			<div>
				<span>직접 조사한 칸</span>
				<b><?php echo $stats['researched']; ?> <small>/ <?php echo $stats['cells']; ?></small></b>
				<small>나머지는 기본값</small>
			</div>
		</div>

		<p class="legend">
			<span class="sample is-default">25,000</span> 회색 = 기본값 (조사 전 : <?php echo h( implode( " · ", $ruleText ) ); ?>)
			<span class="sample is-checked">25,000</span> 검정 = 직접 조사한 가격 · 가격 칸을 누르면 배달가를 입력할 수 있습니다.
		</p>

		<!-- 비교표 : 행 = 메뉴, 열 = 매장가 + 배달앱 -->
		<div class="table-wrap">
			<table class="compare">
				<thead>
					<tr>
						<th scope="col" class="menu-col">메뉴</th>
						<th scope="col" class="num">매장가</th>
<?php foreach ( $platforms as $p ) { ?>
						<th scope="col" class="num"><?php echo h( $p['name'] ); ?></th>
<?php } ?>
					</tr>
				</thead>
<?php foreach ( $groups as $g ) { ?>
				<tbody>
					<tr class="group">
						<th scope="rowgroup" colspan="<?php echo $columnCount; ?>"><?php echo h( $g['brand'] ); ?> · <?php echo h( $g['category'] ); ?> <small><?php echo count( $g['menus'] ); ?>개</small></th>
					</tr>
<?php foreach ( $g['menus'] as $m ) { ?>
					<tr>
						<th scope="row" class="menu-col">
							<div class="menu">
								<?php echo menuImage( $m['image'], $m['name'] ); ?>
								<div>
									<b><?php echo h( $m['name'] ); ?></b>
<?php if ( $m['size_info'] !== null && $m['size_info'] !== "" ) { ?>
									<small><?php echo h( $m['size_info'] ); ?></small>
<?php } ?>
								</div>
							</div>
						</th>
						<td class="num store"><?php echo won( $m['store_price'] ); ?></td>
<?php foreach ( $platforms as $p ) {
	$cell = $m['prices'][ $p['platform_id'] ] ?? null;
	$editUrl = "price.php?brand=" . $m['brand_id'] . "&platform=" . $p['platform_id'] . "#menu-" . $m['menu_id'];
	if ( $cell === null ) { ?>
						<td class="num cell none"><a href="<?php echo h( $editUrl ); ?>" title="배달가 없음 · 눌러서 입력">-</a></td>
<?php } else {
		$diff = $cell['price'] - $m['store_price'];
		if ( $cell['is_default'] )
		{
			$tip = "기본값 (조사 전)";
		}
		else
		{
			$tip = "직접 조사" . ( $cell['checked_on'] ? " · " . $cell['checked_on'] : "" )
				. ( $cell['store_name'] ? " · " . $cell['store_name'] : "" )
				. ( $cell['delivery_fee'] !== null ? " · 배달팁 " . won( $cell['delivery_fee'] ) . "원" : "" );
		} ?>
						<td class="num cell <?php echo $cell['is_default'] ? "is-default" : "is-checked"; ?>">
							<a href="<?php echo h( $editUrl ); ?>" title="<?php echo h( $p['name'] . " · " . $tip ); ?>">
								<span class="price"><?php echo won( $cell['price'] ); ?></span>
								<span class="diff <?php echo diffClass( $diff ); ?>"><?php echo diffText( $diff ); ?></span>
<?php if ( !$cell['is_default'] && $cell['delivery_fee'] !== null ) { ?>
								<small>팁 <?php echo won( $cell['delivery_fee'] ); ?></small>
<?php } ?>
							</a>
						</td>
<?php }
} ?>
					</tr>
<?php } ?>
				</tbody>
<?php } ?>
			</table>
		</div>

		<!-- 매장가 출처 -->
		<ul class="source">
<?php foreach ( $brands as $b ) { if ( $b['homepage'] === null || $b['homepage'] === "" ) continue; ?>
			<li><?php echo h( $b['name'] ); ?> 매장가 : <a href="<?php echo h( $b['homepage'] ); ?>" target="_blank" rel="noopener">공식 홈페이지</a> 권장소비자가격<?php echo $b['checked_on'] ? " (" . h( $b['checked_on'] ) . " 확인)" : ""; ?></li>
<?php } ?>
		</ul>
<?php } ?>
<?php include( __DIR__ . "/footer.php" ); ?>
