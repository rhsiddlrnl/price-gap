<?php
	include( __DIR__ . "/util.php" );

	// 삭제 후보 메뉴 목록 : 예제 delete.php 처럼 selectSQL.php 를 include (검색 조건 없이 전체 메뉴)
	$keyword   = "";
	$foodType  = "";
	$brandId   = 0;
	$category  = "";
	$logAction = "delete_view";
	include( __DIR__ . "/selectSQL.php" );

	$selected = intParam( "menu" );

	$pageTitle = "메뉴 삭제";
	$navActive = "delete";
	$flash = takeFlash();
	include( __DIR__ . "/header.php" );
?>
		<h1>메뉴 삭제</h1>
		<p class="lead">판매가 끝났거나 잘못 넣은 메뉴를 지웁니다. 그 메뉴의 배달가 기록도 함께 지워집니다.</p>

<?php if ( $error !== null ) { ?>
		<p class="error">DB 오류 : <?php echo h( $error ); ?></p>
<?php } else { ?>
		<!-- 예제 : 삭제할 ID 를 직접 입력 → 오타로 다른 레코드 삭제, 1' or '1'='1 입력 시 전체 삭제
		     수정 : 목록에서 고르고, 숫자 번호만 POST 로 전달 -->
		<form class="filters" method="post" action="deleteSQL.php" onsubmit="return confirm( '선택한 메뉴와 그 메뉴의 배달가 기록을 삭제할까요?' );">
			<select name="menu_id" required aria-label="삭제할 메뉴">
				<option value="">삭제할 메뉴 선택 (<?php echo $stats['menus']; ?>개)</option>
<?php foreach ( $groups as $g ) { ?>
				<optgroup label="<?php echo h( $g['brand'] . " · " . $g['category'] ); ?>">
<?php foreach ( $g['menus'] as $m ) { ?>
					<option value="<?php echo $m['menu_id']; ?>"<?php echo ( (int)$m['menu_id'] === $selected ) ? " selected" : ""; ?>><?php echo h( $m['name'] ); ?> (<?php echo won( $m['store_price'] ); ?>원)</option>
<?php } ?>
				</optgroup>
<?php } ?>
			</select>
			<button class="btn danger" type="submit">삭제</button>
		</form>
		<p class="note">메뉴가 하나도 남지 않은 브랜드는 브랜드 목록에서도 빠집니다. 다시 넣으려면 <a href="insert.php">메뉴 추가</a>나 SQL.txt 를 사용하세요.</p>
<?php } ?>
<?php include( __DIR__ . "/footer.php" ); ?>
