(function ($) {
    'use strict';
    $(document).ready(function () {
        // Lắng nghe sự kiện ajaxComplete của WordPress khi lưu file phương tiện
        $(document).ajaxComplete(function (event, xhr, settings) {
            if (settings.data && settings.data.indexOf('action=save-attachment-compat') !== -1) {
                // Kiểm tra xem dữ liệu gửi đi có chứa trường wpcpv-video-url không
                if (settings.data.indexOf('wpcpv-video-url') !== -1) {
                    var parsedData = new URLSearchParams(settings.data);
                    var attachId = parsedData.get('id');

                    if (attachId) {
                        var inputSelector = '[name="attachments[' + attachId + '][wpcpv-video-url]"]';
                        var $input = $(inputSelector);

                        if ($input.length > 0) {
                            var $container = $input.closest('td, .compat-field-wpcpv-video-url');
                            var $notice = $container.find('.wpcpv-saved-notice');

                            if ($notice.length === 0) {
                                $notice = $('<div class="wpcpv-saved-notice">' + wpcpv_backend_vars.saved_text + '</div>');
                                $input.after($notice);
                            }

                            $notice.stop(true, true).fadeIn(200).delay(3000).fadeOut(300);
                        }
                    }
                }
            }
        });
    });
})(jQuery);
