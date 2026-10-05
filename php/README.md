# 배달하면 얼마 더? : PHP 예제 프로그램 분석 및 수정 (10/6 발표)

[CodeReading101/Web-DB](https://github.com/CodeReading101/Web-DB) 의 PHP 예제(book = 검색·추가·삭제, game = 랭킹)를 분석하고,
**테이블 4개**짜리 새 주제 "매장가 vs 배달앱 가격 비교"에 맞게 수정한 코드입니다. 주제와 테이블 설명은 [상위 README](../README.md) 참고.

---

## 1. 예제 → 수정 대응표

| 예제 파일 | 예제 기능 | 수정 후 파일 | 우리 기능 | SQL | 사용 테이블 |
|---|---|---|---|---|---|
| book/search.php + selectSQL.php | 책 제목 검색 | **search.php + selectSQL.php** | 메뉴 검색 + 매장가 vs 배달앱 비교표 | SELECT, LIKE, JOIN 3개 | brand, menu, platform, delivery_price |
| book/insert.php + insertSQL.php | 새 책 등록 | **insert.php + insertSQL.php** | 메뉴 추가 (새 브랜드·새 음식 포함) + 기본 배달가 자동 생성 | INSERT, INSERT ... SELECT, 트랜잭션 | brand, menu, delivery_price |
| book/delete.php + deleteSQL.php | 책 삭제 (ID 입력) | **delete.php + deleteSQL.php** | 메뉴 삭제 (목록에서 선택) | DELETE, ON DELETE CASCADE | menu, delivery_price, brand |
| (없음) | | **price.php + priceSQL.php** | 배달가 입력·수정, 기본값으로 되돌리기 | INSERT ... ON DUPLICATE KEY UPDATE, UPDATE JOIN | delivery_price |
| game/selectSQL.php | 게임별 점수 랭킹 | **ranking.php** | 배달앱별 평균 차액 + 메뉴별 차액 순위 | GROUP BY, AVG, SUM, ORDER BY | 4개 모두 |
| SQLconstants.php | DB 접속 상수 | **SQLconstants.php** | DB = `PriceGapDB` | | |
| log.php | 접속 로그 함수 (호출 안 됨) | **log.php** | 모든 페이지에서 호출, Action 항목 추가 | | |
| book/SQL.txt | 테이블 1개 + 책 8권 | **SQL.txt** | 테이블 4개 + 교촌치킨 77메뉴 + 기본 배달가 308칸 | CREATE, INSERT, CROSS JOIN | |
| | | util.php, header.php, footer.php, style.css, index.php | 공통 함수, 공통 화면 | | |

예제에 없던 **UPDATE**(배달가 수정)를 더해서 검색·추가·수정·삭제(CRUD)를 모두 갖췄습니다.

---

## 2. 예제 분석 (발표 "분석" 파트)

### 페이지 흐름 (book 예제)
```
search.php ──include──▶ selectSQL.php          select * from book where title like '%검색어%'
   ├─ [새 책 추가] insert.php ──POST──▶ insertSQL.php   INSERT
   └─ [책 삭제]   delete.php ──POST──▶ deleteSQL.php   DELETE   (delete.php 도 selectSQL.php 를 include)
                                     └─ hidden form 을 자바스크립트로 자동 submit ──▶ search.php
```

### 4장 개념과 예제 코드 대응
| 4장 개념 | 예제 코드 위치 |
|---|---|
| `<?php ... ?>` 영역, `header()` 로 문서 타입·인코딩 지정 | 모든 파일 첫 줄 `header('Content-Type: text/html; charset=UTF-8')` |
| `include()` 로 다른 문서 포함 | search.php 가 selectSQL.php 포함, selectSQL.php 가 SQLconstants.php 포함 |
| `$_POST['전달이름']`, `$_SERVER['환경']` | 검색어·입력값 전달, log.php 의 접속 정보 |
| `echo`, 문자열 연결 `.` | selectSQL.php 의 결과 출력, SQL 문 조립 |
| 연결 요청 `mysqli_connect` → 실행 `mysqli_query` → 결과 `mysqli_fetch_array` → 해제 `mysqli_free_result`, `mysqli_close` | selectSQL.php, insertSQL.php, deleteSQL.php |

### 우리 코드에서 같은 순서
`dbConnect()`(연결 요청·승인) → `mysqli_prepare` / `mysqli_stmt_bind_param` / `mysqli_stmt_execute`(SQL 실행 요청) →
`mysqli_stmt_get_result` / `mysqli_fetch_all`(실행 결과) → `mysqli_free_result`, `mysqli_close`(연결 해제). 모두 `util.php` 에 모아 두었습니다.

---

## 3. 발견한 문제점과 수정 내용 (발표 "수정" 파트)

| # | 예제의 문제 | 재현 방법 | 수정 |
|---|---|---|---|
| 1 | **SQL Injection** : 입력값을 SQL 문자열에 그대로 이어 붙임 (book 검색·추가·삭제, game 랭킹) | delete.php 에 `1' or '1'='1` 입력 → `delete from book where id = '1' or '1'='1'` → **책 전체 삭제** | 모든 SQL 을 `mysqli_prepare` + `?` 로 변경 (`util.php` 의 `dbSelect`, `dbExecute`) |
| 2 | 작은따옴표가 든 제목은 등록 안 됨 | 제목에 `Can't Stop` 입력 → SQL 문법 오류 | 1번 수정으로 해결 |
| 3 | **등록 결과가 반대로 나옴** : `if( !$result )` 이면 "등록하였습니다" (game/insertSQL.php 도 같음) | 정상 등록 → `$message` 에 "등록할 수 없습니다" 가 담김 (4번 때문에 화면엔 안 보임), game 은 "등록 실패!" 출력 | 성공 여부를 예외(try-catch)와 바뀐 행 수로 판단 |
| 4 | **결과 메시지가 화면에 안 나옴** : PHP 판 search.php 는 `showMessage( '' )` 로 빈 문자열 고정 (JSP 판은 전달받은 message 를 넘김) | 등록·삭제 후 아무 알림 없음 | 세션에 한 번만 담아 다음 화면 위에 표시 (flash) |
| 5 | 없는 ID 를 지워도 "삭제하였습니다" : DELETE 는 지운 행이 0개여도 `true` | delete.php 에 `999` 입력 | `mysqli_stmt_affected_rows` 로 지운 행 수 확인 |
| 6 | PHP 8.1 부터 mysqli 오류가 **예외**로 바뀌어 `or die(...)`, `if( !$result )` 가 실행되지 않음 | 같은 ID 로 두 번 등록 → Fatal error (또는 빈 500 화면) | `try-catch` 로 잡아서 알기 쉬운 메시지 (중복은 1062 번 오류로 구분) |
| 7 | 처음 접속하면 `$_POST['message']` 가 없어서 경고 | search.php 첫 접속 (display_errors 켜진 서버) → Undefined array key 경고 | `param()` : 값이 없으면 기본값 (`??`) |
| 8 | **XSS** : DB 값과 메시지를 이스케이프 없이 출력 | 제목에 `<script>alert(1)</script>` 등록 → 목록을 볼 때마다 실행 | 출력할 때 `h()` = `htmlspecialchars` |
| 9 | **log.php 를 어느 페이지도 호출하지 않음** + 경로 `/var/www/html/book/php/log.txt` 고정 + 출력 뒤 `session_start()` + 시간대 없음(UTC) | log.txt 가 쌓이지 않음 | 모든 페이지에서 `writeLog( Action, Message )` 호출, `__DIR__` 경로, 세션은 출력 전에 시작, `Asia/Seoul` |
| 10 | 결과 전달을 hidden form 자동 submit(POST)으로 함 | 등록 후 F5 → "양식 다시 제출" 경고, 다시 등록 시도 | Post/Redirect/Get : 처리 후 `header("Location: ...")` |
| 11 | 검색어가 없으면 `_%` 를 넣는 방식, 사용자가 입력한 `%` `_` 도 와일드카드 | `_` 검색 → 전체 목록 | `likePattern()` 으로 `%` `_` `\` 이스케이프 |
| 12 | ID 를 사람이 직접 입력 (AUTO_INCREMENT 없음), 삭제도 ID 입력 | 중복 ID → DB 오류, 오타 → 엉뚱한 책 삭제 | `AUTO_INCREMENT`, 삭제는 목록(select)에서 고르고 숫자만 받음 |
| 13 | game 랭킹 : `$rank++` 라서 점수가 같아도 순위가 다름, `$_POST['title']` 을 그대로 출력 | 같은 점수 2명 → 1위, 2위 | 같은 값이면 같은 순위, ORDER BY 는 정해진 두 문장 중에서만 선택 |
| 14 | include 된 selectSQL.php 가 HTML 출력 뒤에 다시 `header()` 호출 | output_buffering 이 꺼진 서버 → "headers already sent" 경고 | `header()` 는 `util.php` 에서 출력 전에 한 번만 |

> 참고 : 강의자료 21쪽 INSERT 예제도 `if( !$result )` 이면 "INSERT 성공"으로 3번과 같은 문제가 있고, 21·22쪽은 PHP 에 없는 JSP 의 `out.println` 을 씁니다.

---

## 4. 서버에 올리고 실행하기 (AWS)

1. **DB** : phpMyAdmin 에서 `PriceGapDB` 를 만들고 `SQL.txt` 의 2) ~ 4) SQL 실행 → `SELECT COUNT(*) FROM delivery_price;` 가 **308** 이면 성공
2. **파일 복사** : 강의자료처럼 `/home/project/` 에 clone → `http://IP주소/price-gap/php/search.php` (Xftp 로 `php` 폴더만 올려도 됨)
   ```bash
   cd /home/project/
   sudo git clone https://github.com/rhsiddlrnl/price-gap.git
   ```
3. **DB 접속 정보** : `sudo vi SQLconstants.php` 에서 `$mySQL_id`, `$mySQL_password` 수정 (GitHub 에는 비밀번호를 올리지 않기)
4. **로그 파일 권한** (웹 서버가 log.txt 를 쓸 수 있도록)
   ```bash
   cd /home/project/price-gap/php
   sudo touch log.txt && sudo chmod 666 log.txt
   ```
5. **접속** : `http://IP주소/price-gap/php/` (자동으로 search.php)
6. **로그 확인** : `tail -f log.txt` 를 띄워 두고 검색·입력·삭제를 하며 Action 별로 줄이 쌓이는지 확인

> mysqli 가 없다는 오류가 나면 `sudo apt install php-mysql` 후 `sudo systemctl restart apache2`.
> 이 PC 에는 PHP·MySQL 이 없어서 **아직 실제 서버에서 실행해 보지 않았습니다.** 처음 올렸을 때 오류가 나면 화면의 "DB 오류" 메시지나 `/var/log/apache2/error.log` 를 확인하세요.

---

## 5. 시연 시나리오 (약 3분)

1. **원본 예제의 문제** : book/delete.php 에 `1' or '1'='1` → 책 목록이 모두 사라짐 / 새 책 등록·삭제 → 결과 알림이 안 나옴
2. **가격 비교** (`search.php`) : 요약 카드 → "허니" 검색 → 허니콤보 매장가 23,000 vs 배달앱 25,000(+2,000, 회색 = 기본값), 자사앱 23,000
3. **배달가 입력** (`price.php`) : 허니콤보 칸을 눌러 이동 → 배달의민족 가격을 실제 조사값으로 고치면 자동 체크 → 저장 → 비교표에서 검정 글씨로 바뀜
4. **차액 랭킹** (`ranking.php`) : 배달앱별 평균 차액, 비율순 순위 (싼 싱글윙일수록 비율이 큼) → '직접 조사한 가격만' 체크
5. **확장성** (`insert.php`) : 새 브랜드(예: BBQ, 치킨, 황금올리브치킨 23,000원) 추가 → 비교표에 브랜드가 생기고 기본 배달가 4개 자동 생성 → `delete.php` 로 삭제
6. **공격 재시도** : 검색창에 `' or '1'='1`, `<script>alert(1)</script>` → 그냥 글자로 검색됨
7. **log.txt** : 방금 한 행동이 `search`, `price_save`, `menu_add`, `menu_delete` 로 기록된 것 → 11/17 로그데이터 분석으로 이어짐

---

## 6. 발표 구성 (추천)

1. 표지 : 팀 C · 배달하면 얼마 더? · PHP 예제 분석 및 수정
2. 주제 변경 : 테이블 4개 제한 → 매장가 vs 배달가, 교촌치킨을 고른 이유 (공식 가격 77종 + 2025년 이중가격 보도)
3. 테이블 4개 ERD 와 확장 방법 (음식 = 컬럼, 배달앱 = 행)
4. 예제 구조 : 페이지 흐름, 4장 개념과 코드 대응
5. 문제점 발견 : SQL Injection 시연, 반대로 나오는 메시지, 호출되지 않는 log.php (위 표 1 ~ 14 중 핵심 4 ~ 5개)
6. 수정 코드 before / after : Prepared Statement, try-catch, `h()`, Post/Redirect/Get, affected rows
7. 실행 화면 : 비교표 → 배달가 입력 → 랭킹 → 메뉴 추가
8. 다음 단계 : 실제 배달가 조사(같은 매장 기준), 다른 브랜드·음식 추가, UI 개선(10/13), 로그 분석
