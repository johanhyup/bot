// 모든 AJAX 요청에 대한 오류 로깅
$(document).ajaxError(function(event, xhr, settings, thrownError) {
    console.error('AJAX 오류 발생:', {
        url: settings.url,
        method: settings.type,
        status: xhr.status,
        statusText: xhr.statusText,
        responseText: xhr.responseText.substring(0, 500), // 처음 500자만
        error: thrownError
    });
    
    // 503 오류 전용 처리
    if (xhr.status === 503) {
        console.warn('503 Service Unavailable 감지됨');
        
        // 서버에 오류 정보 전송
        $.post('/php/log_ajax_error.php', {
            url: settings.url,
            status: xhr.status,
            response: xhr.responseText.substring(0, 200)
        }).fail(function() {
            console.error('오류 로깅 실패');
        });
    }
});
