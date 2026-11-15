/**
 * 배달하면 얼마 더? — 구글 설문지 2개 (최신 웹사이트 1개로 테스트)
 *   createTaskForm        : 태스크 수행 기록 (태스크를 하면서 답 · 난이도 기록)
 *   createUsabilityForm   : 사용성 평가 설문 (태스크를 모두 마친 뒤 10문항 + 서술)
 *   convertExistingForms  : 이미 만든 두 설문(기본/개선 2개 사이트용)을 사이트 1개용으로 바꾸기
 *   setNameExample        : 이미 만든 두 설문의 '학번이름' 입력 예시를 NAME_EXAMPLE 로 바꾸기
 * 두 설문은 '학번이름'으로 같은 참가자를 연결한다.
 *
 * 사용법
 *  1. https://script.google.com → 새 프로젝트 → 이 파일 내용을 전부 붙여넣기
 *  2. 아래 SITE 주소 확인 (AWS 를 켤 때마다 공인 IP 가 바뀌면 수정)
 *  3. 새로 만들 때 : createTaskForm 실행 → createUsabilityForm 실행 (처음 한 번 권한 허용)
 *     이미 만든 설문을 고칠 때 : convertExistingForms 실행 (응답이 있는 설문은 건드리지 않음)
 *  4. 실행 로그의 '응답 주소'(참가자용) · '편집 주소'(팀용) · '응답 시트' 확인
 */
const SITE = 'http://52.55.137.81/price-gap/php/search.php';   // 테스트할 최신 웹사이트

// 이미 만든 설문 (2026-11-14 생성)
const TASK_FORM_ID      = '1ROUU-NlgU3TrDWxuMHTKi8IMHSTPRLHxPCNqrWOR1ao';
const USABILITY_FORM_ID = '1kgZLO77p7K5NfDJklgjzaRf334UvkhlFZS2rK1I_9Cs';

const NAME_EXAMPLE = '예: 202911999 홍길동';   // 학번이름 입력 예시

const TASKS =
  'T1  친구들과 허니콤보를 시키려고 해요. 어느 앱이 가장 싸고, 다른 앱은 매장보다 얼마나 더 내야 하나요?\n' +
  'T2  교촌치킨 메뉴 중 배달의민족으로 시키면 매장가보다 \'비율로\' 가장 많이 비싸지는 메뉴와 그 비율은?\n' +
  'T3  (조사 담당 팀원으로서) 쿠팡이츠 \'교촌치킨 테스트점\' 오늘 가격 — 허니콤보 25,500원 · 간장콤보 24,500원 · 배달팁 3,000원 — 을 저장하세요.\n' +
  '※ 각 태스크는 화면 왼쪽 위 로고를 눌러 첫 화면에서 시작해 주세요.';

const TASK_DESC =
  '웹서버개발 C조 \'배달하면 얼마 더?\' 사용성 테스트의 태스크 기록지입니다.\n' +
  '웹사이트에서 태스크 3개를 하고, 하나 끝날 때마다 답을 적어 주세요.\n' +
  '태스크를 모두 마친 뒤에는 진행자가 주는 \'사용성 평가 설문\'에 답해 주세요.\n\n' +
  '웹사이트: ' + SITE;
const TASK_PAGE = '웹사이트 태스크';
const TASK_HELP = '주소: ' + SITE + '\n새 시크릿 창에서 열고 아래 태스크를 순서대로 해 주세요.\n\n' + TASKS;

const USABILITY_DESC =
  '웹서버개발 C조 \'배달하면 얼마 더?\'(매장가 vs 배달앱 가격 비교) 사용성 평가 설문입니다.\n' +
  '웹사이트에서 태스크를 모두 마친 뒤 답해 주세요. (약 5분)\n\n' +
  '웹사이트: ' + SITE + '\n\n' +
  '학번이름은 결과 분석 후 P01, P02 … 로 익명 처리합니다.';
const SCALE_PAGE = '사용성 평가';
const SCALE_HELP = '주소: ' + SITE + '\n1 전혀 아니다 · 2 아니다 · 3 보통 · 4 그렇다 · 5 매우 그렇다';

// 사용성 10문항 (5점 척도) — 발표자료 04 사용성 평가 문항과 같은 순서
const ITEMS = [
  ['이 웹사이트의 화면은 처음 써도 사용하기 쉽나요?', true],
  ['원하는 메뉴의 배달앱별 가격을 빠르게 찾을 수 있나요?', true],
  ['매장가와 배달가의 차이(원 · %)를 한눈에 이해할 수 있나요?', true],
  ['어느 배달앱이 가장 싼지 쉽게 알 수 있나요?', true],
  ['순위 · 정렬의 기준(차액 큰 순 등)이 분명한가요?', true],
  ['가격이 직접 조사한 값인지 추정값인지, 언제 조사했는지 알 수 있나요?', true],
  ['배달가 입력 · 저장을 실수 없이 할 수 있었나요?', true],
  ['다음에 다시 써도 사용법이 기억날 것 같나요?', true],
  ['휴대폰에서도 보기 편한가요? (휴대폰으로 참여한 경우만)', false],
  ['이 웹사이트에 전반적으로 만족하나요?', true],
];

// ------------------------------------------------------------------ 1. 태스크 수행 기록
function createTaskForm() {
  const form = FormApp.create('배달하면 얼마 더? 태스크 수행 기록 (C조)');
  form.setDescription(TASK_DESC);
  form.setProgressBar(true);

  form.addSectionHeaderItem().setTitle('참가자');
  form.addTextItem().setTitle('학번이름').setHelpText(NAME_EXAMPLE + ' — 사용성 평가 설문과 연결하는 데 사용').setRequired(true);
  form.addMultipleChoiceItem().setTitle('참여 기기').setChoiceValues(['PC', '휴대폰']).setRequired(true);

  form.addPageBreakItem().setTitle(TASK_PAGE).setHelpText(TASK_HELP);
  form.addTextItem().setTitle('세션 번호').setHelpText('웹사이트 화면 맨 아래에 보이는 4글자 (로그와 연결하는 데만 사용)');
  form.addMultipleChoiceItem().setTitle('T1 가장 싸게 시킬 수 있는 앱은?')
      .setChoiceValues(['배달의민족', '쿠팡이츠', '요기요', '자사앱', '찾지 못함']).setRequired(true);
  form.addTextItem().setTitle('T1 다른 배달앱은 매장가보다 얼마나 더 비싼가요?').setHelpText('예: 1,000원');
  form.addTextItem().setTitle('T2 비율로 가장 많이 비싸지는 메뉴와 그 비율').setHelpText('예: ○○메뉴 10%');
  form.addMultipleChoiceItem().setTitle('T3 배달가를 저장했나요?')
      .setChoiceValues(['저장했다', '저장하지 못했다']).setRequired(true);
  form.addGridItem().setTitle('태스크별 난이도')
      .setRows(['T1 가격 비교', 'T2 차액 랭킹', 'T3 배달가 입력'])
      .setColumns(['1 매우 어려움', '2', '3', '4', '5 매우 쉬움']).setRequired(true);
  finish(form, '배달하면 얼마 더? 태스크 수행 기록 (응답)');
}

// ------------------------------------------------------------------ 2. 사용성 평가 설문
function createUsabilityForm() {
  const form = FormApp.create('배달하면 얼마 더? 사용성 평가 (C조)');
  form.setDescription(USABILITY_DESC);
  form.setProgressBar(true);

  form.addSectionHeaderItem().setTitle('참가자');
  form.addTextItem().setTitle('학번이름').setHelpText(NAME_EXAMPLE).setRequired(true);
  form.addMultipleChoiceItem().setTitle('배달앱으로 음식을 주문하는 빈도')
      .setChoiceValues(['거의 안 함', '한 달에 1~2번', '일주일에 1~2번', '일주일에 3번 이상']).setRequired(true);

  form.addPageBreakItem().setTitle(SCALE_PAGE).setHelpText(SCALE_HELP);
  ITEMS.forEach(function (it, i) {
    form.addScaleItem().setTitle((i + 1) + '. ' + it[0])
        .setBounds(1, 5).setLabels('전혀 아니다', '매우 그렇다').setRequired(it[1]);
  });

  form.addPageBreakItem().setTitle('의견');
  form.addParagraphTextItem().setTitle('이 웹사이트에서 가장 마음에 드는 점').setRequired(true);
  form.addParagraphTextItem().setTitle('이 웹사이트가 개선되어야 할 점').setRequired(true);
  finish(form, '배달하면 얼마 더? 사용성 평가 (응답)');
}

// ------------------------------------------------------------------ 3. 이미 만든 설문을 사이트 1개용으로
// '개선 웹사이트' 섹션과 그 문항 · '먼저 사용하는 웹사이트' 문항을 지우고,
// '기본 웹사이트' 섹션 이름 · 안내와 문항 앞 '[기본] '을 바꾼다. 응답이 1개라도 있으면 건드리지 않는다.
function convertExistingForms() {
  convertForm_(TASK_FORM_ID, TASK_DESC, TASK_PAGE, TASK_HELP, ['먼저 사용하는 웹사이트']);
  convertForm_(USABILITY_FORM_ID, USABILITY_DESC, SCALE_PAGE, SCALE_HELP, []);
}

function convertForm_(id, desc, pageTitle, pageHelp, dropTitles) {
  const form = FormApp.openById(id);
  const responses = form.getResponses().length;
  if (responses > 0) {
    Logger.log(form.getTitle() + ' : 응답 ' + responses + '개가 있어서 바꾸지 않았습니다.');
    return;
  }
  form.setDescription(desc);
  const remove = [];
  let inImproved = false;
  form.getItems().forEach(function (item) {
    const title = item.getTitle();
    if (item.getType() === FormApp.ItemType.PAGE_BREAK) {
      inImproved = (title === '개선 웹사이트');
      if (title === '기본 웹사이트') item.asPageBreakItem().setTitle(pageTitle).setHelpText(pageHelp);
    }
    if (inImproved || dropTitles.indexOf(title) >= 0) { remove.push(item); return; }
    if (title.indexOf('[기본] ') === 0) item.setTitle(title.substring(5));
    if (item.getType() === FormApp.ItemType.PARAGRAPH_TEXT && item.getHelpText().indexOf('기본 · 개선') >= 0) item.setHelpText('');
  });
  remove.reverse().forEach(function (item) { form.deleteItem(item); });
  Logger.log(form.getTitle() + ' : 문항 ' + remove.length + '개 삭제, 남은 문항 ' + form.getItems().length + '개');
  form.getItems().forEach(function (item) { Logger.log('  - ' + item.getTitle()); });
}

// ------------------------------------------------------------------ 4. 학번이름 입력 예시 바꾸기
function setNameExample() {
  [TASK_FORM_ID, USABILITY_FORM_ID].forEach(function (id) {
    const form = FormApp.openById(id);
    form.getItems().forEach(function (item) {
      if (item.getTitle() !== '학번이름') return;
      const rest = item.getHelpText().replace(/^예: [^—]*/, '').trim();   // '— 사용성 평가 설문과 …' 부분은 그대로
      item.setHelpText(NAME_EXAMPLE + (rest ? ' ' + rest : ''));
      Logger.log(form.getTitle() + ' : ' + item.getHelpText());
    });
  });
}

// ------------------------------------------------------------------ 응답 시트 연결 · 주소 출력
function finish(form, sheetName) {
  const sheet = SpreadsheetApp.create(sheetName);
  form.setDestination(FormApp.DestinationType.SPREADSHEET, sheet.getId());
  Logger.log(form.getTitle());
  Logger.log('응답 주소 (참가자용): ' + form.getPublishedUrl());
  Logger.log('편집 주소 (팀용): ' + form.getEditUrl());
  Logger.log('응답 시트: ' + sheet.getUrl());
}
