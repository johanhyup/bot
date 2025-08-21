console.log('로그인 디버그 스크립트 로드됨');

// 모든 AJAX 요청 전에 로깅
$(document).ajaxSend(function(event, xhr, settings) {
    console.log('AJAX 요청 시작:', {
        url: settings.url,
        method: settings.type,
        data: settings.data
    });
});

// 모든 AJAX 완료 시 로깅
$(document).ajaxComplete(function(event, xhr, settings) {
    console.log('AJAX 완료:', {
        url: settings.url,
        status: xhr.status,
        statusText: xhr.statusText
    });
});

// 페이지 로드 시 기존 login.js 확인
$(document).ready(function() {
    console.log('페이지 로드 완료');
    console.log('jQuery 버전:', $.fn.jquery);
});
