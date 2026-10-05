<?php
	// 공통 화면 위쪽 : include 하기 전에 $pageTitle, $navActive, $flash 를 정해 둘 것
	$menuLinks = array(
		"search"  => array( "search.php",  "가격 비교" ),
		"ranking" => array( "ranking.php", "차액 랭킹" ),
		"price"   => array( "price.php",   "배달가 입력" ),
		"insert"  => array( "insert.php",  "메뉴 추가" ),
		"delete"  => array( "delete.php",  "메뉴 삭제" )
	);
?>
<!DOCTYPE html>
<html lang="ko">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo h( $pageTitle ); ?> · 배달하면 얼마 더?</title>
	<link rel="stylesheet" href="style.css">
</head>
<body>
	<header class="topbar">
		<div class="wrap bar">
			<a class="logo" href="search.php"><span class="logo-mark">₩</span>배달하면 얼마 더?</a>
			<nav>
<?php foreach ( $menuLinks as $key => $link ) { ?>
				<a href="<?php echo $link[0]; ?>"<?php echo ( $key === $navActive ) ? ' class="on" aria-current="page"' : ''; ?>><?php echo $link[1]; ?></a>
<?php } ?>
			</nav>
		</div>
	</header>
	<main class="wrap">
<?php if ( $flash !== null ) { ?>
		<div class="flash <?php echo h( $flash['type'] ); ?>" role="status"><?php echo h( $flash['message'] ); ?></div>
<?php } ?>
