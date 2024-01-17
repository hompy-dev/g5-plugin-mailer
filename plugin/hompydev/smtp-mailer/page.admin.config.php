<?php
if (!defined('_GNUBOARD_')) exit;

auth_check_menu($auth, self::ADMIN_MENU_CODE, 'r');

add_stylesheet('<link rel="stylesheet" href="'.G5_PLUGIN_URL.'/hompydev/smtp-mailer/style.css">', 0);
?>

<div id="SMTPConfig">
    <form method="post" autocomplete="off">
        <input type="hidden" name="token" value="" id="token">
        <input type="hidden" name="poster" value="smpt_config">
        <div class="row providers">
            <label class="label-naver" title="네이버"><input type="radio" name="provider" value="naver" <?php if($smtpConfig['provider']=='naver') echo 'checked'?>></label>
            <label class="label-daum" title="다음"><input type="radio" name="provider" value="daum" <?php if($smtpConfig['provider']=='daum') echo 'checked'?>></label>
            <label class="label-nate" title="네이트"><input type="radio" name="provider" value="nate" <?php if($smtpConfig['provider']=='nate') echo 'checked'?>></label>
            <label class="label-gmail" title="구글"><input type="radio" name="provider" value="gmail" <?php if($smtpConfig['provider']=='gmail') echo 'checked'?>></label>
            <label class="label-unuse" title="사용안함"><input type="radio" name="provider" value="" <?php if(!$smtpConfig['provider']) echo 'checked'?>> 사용 안 함</label>
        </div>
        <div class="row">
            <label class="title">계정 아이디</label>
            <input type="text" name="account_id" value="<?php echo $smtpConfig['account_id']?>" class="string" size="22" autocomplete="off" required>
        </div>
        <div class="row">
            <label class="title">계정 비밀번호</label>
            <span class="pw_wrapper">
                <input type="password" name="account_pw" value="<?php echo $smtpConfig['account_pw']?>" class="string" autocomplete="off" size="18" required>
                <i class="fa fa-eye-slash fa-lg" title="비밀번호 보기/숨김"></i>
            </span>
        </div>
        <div class="row">
            <label class="title">보내는 메일</label>
            <input type="text" name="from_email" value="<?php echo $smtpConfig['from_email']?>" class="string" size="22" required>
            <span class="frm_info">선택한 계정에 등록된, 사용가능한 메일</span>
        </div>
        <div class="row">
            <label class="title">발송자 이름</label>
            <input type="text" name="from_name" value="<?php echo $smtpConfig['from_name']; ?>" class="string" size="22" required>
            <span class="frm_info">받는 메일에 보이는 발송자 이름을 입력</span>
        </div>
        <div class="row">
            <label class="title">보낸 메일함</label>
            <?php if (!$this->imapEnable) { ?>
            <span class="frm_info noimap">
                <i class="fa fa-exclamation-triangle" aria-hidden="true"></i>
                <a href="https://www.php.net/manual/en/book.imap.php" target="_blank"><u>'imap' php extension</u></a>이 설치되지 않았거나 사용이 불가합니다
            </span>
            <?php } else { ?>
            <span class="radio">
                <label title="사용함"><input type="radio" name="save_sentmail" value="1" <?php if($smtpConfig['save_sentmail']) echo 'checked'?>> 사용함</label> &nbsp;
                <label title="사용 안 함"><input type="radio" name="save_sentmail" value="" <?php if(!$smtpConfig['save_sentmail']) echo 'checked'?>> 사용 안 함</label>
            </span>
            <span class="frm_info">발송 후 보낸 메일함에 저장</span>
            <?php } ?>
        </div>
        <div class="row btns">
            <!-- <button type="button" class="btn_testmail btn btn_02"><i class="fa fa-envelope-o" aria-hidden="true"></i> 발송 테스트</button> -->
            <button type="submit" class="btn_submit btn"><i class="fa fa-floppy-o" aria-hidden="true"></i> 저장 <i class="fa fa-spinner fa-pulse fa-fw"></i></button>
            <span id="saveResultMsg"></span>
        </div>
    </form>
</div>

<script>
    (function($) {
        const wrapper = $('#SMTPConfig');
        let defaultForm;
        let currentPwdVisible = false;
        let currentDisabled = false;

        // 초기화
        function init() {
            defaultForm = wrapper.find('form').clone(); // 최초 폼 복사 (속성 비교용)
            if (!getCurrentProvider()) disableForm(); // 선택된 공급자 없으면 폼 비활성화
        }

        // 선택된 공급자 값
        function getCurrentProvider() {
            return wrapper.find('input[name=provider]:checked').val();
        }

        // 폼 활성/비활성 처리
        function disableForm(isDisable = true) {
            currentDisabled = isDisable;
            isDisable ? wrapper.addClass('disabled') : wrapper.removeClass('disabled');
            const elements = wrapper.find('form').find('input[type=text], input[type=password], input[type=radio], select').not('[name=provider]');
            elements.each(function(i, el) {
                $(el).prop('disabled', isDisable);
                if (isDisable) {
                    $(el).prop('required', false);
                } else {
                    if ( defaultForm.find('[name=' + el.name + ']').prop('required') ) $(el).prop('required', true);
                }
            });
        }

        // 비밀번호 보기/숨김 처리
        function visiblePassword(pwBox, isVisible = true) {
            isVisible ? pwBox.addClass('visible') : pwBox.removeClass('visible');
            pwBox.find('input').attr('type', isVisible ? 'text' : 'password');
            currentPwdVisible = isVisible;
        }

        // 폼 저장 결과 메세지 출력
        function messageOn(box, msg) {
            box.addClass('on').text(msg);
            const timer = setTimeout(function() {
                box.removeClass('on').text('');
            }, 4000);
        }

        // 공급자 변경시
        wrapper.on('change', 'input[name=provider]', function(e) {
            if (currentDisabled != !getCurrentProvider()) disableForm(!getCurrentProvider() ? true : false);
        });

        // 비밀번호 보기/숨김 아이콘 클릭시
        wrapper.on('click', '.pw_wrapper > i', function(e) {
            e.preventDefault();
            visiblePassword($(this).parent(), !currentPwdVisible);
        });
        $(document).on('click focusin', function(e) {
            if (!currentPwdVisible || $(e.target).parents('.pw_wrapper').length) return;
            visiblePassword(wrapper.find('.pw_wrapper'), false);
        });

        // 'Enter'키 방지 - 'Tab'키로 치환 ('submit' 제외)
        wrapper.on('keydown', 'input', function(e) {
            if (e.keyCode !== 13) return;
            e.preventDefault();
            const focusable = wrapper.find('input,a,select,button,textarea').not(":disabled").filter(':visible');
            const next = focusable.eq(focusable.index(this)+1);
            if (next.length) next.focus().select();
        });

        wrapper.on('keyup paste', 'input', function(e) {
            $(this).val($.trim(e.currentTarget.value));
        });

        // 폼 제출 ('확인' 버튼 클릭시)
        wrapper.on('submit', 'form', function(e) {
            e.preventDefault();
            const btn = $(this).find('button:submit');
            btn.prop('disabled', true).addClass('loading');
            $.post(document.location.href, $(this).serialize(), function(data) {
                if (data.result == 'success') {
                    if (data.message) messageOn($('#saveResultMsg'), data.message);
                } else {
                    if (data.message) alert(data.message);
                }
                btn.prop('disabled', false).removeClass('loading');
            });
        });

        // 초기화 호출
        init();
    })(jQuery);
</script>
