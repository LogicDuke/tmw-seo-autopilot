jQuery(document).ready(function ($) {
    var nonce = (window.tmwseoAdmin && window.tmwseoAdmin.nonce) ? window.tmwseoAdmin.nonce : '';

    $('#tmwseo-generate-content').on('click', function () {
        var $btn = $(this);
        var postId = $btn.data('post-id');
        var $spinner = $btn.next('.spinner');

        $btn.prop('disabled', true);
        $spinner.addClass('is-active');

        $.post(ajaxurl, {
            action: 'tmwseo_generate_model_content',
            post_id: postId,
            nonce: nonce
        }).done(function (response) {
            if (response.success) {
                $('textarea[name="tmwseo_ai_content"]').val(response.data.content);
                $('.tmwseo-generated-content').show();
                if (response.data.timestamp) {
                    $('.tmwseo-generated-date').text('Generated on: ' + response.data.timestamp);
                }
            } else {
                alert('Error: ' + (response.data && response.data.message ? response.data.message : 'Request failed'));
            }
        }).fail(function () {
            alert('Error: request failed');
        }).always(function () {
            $spinner.removeClass('is-active');
            $btn.prop('disabled', false);
        });
    });

    $('#tmwseo-generate-titles').on('click', function () {
        console.log('Button clicked');
        var $btn = $(this);
        var postId = $btn.data('post-id');
        console.log('Post ID:', postId);
        console.log('Nonce:', nonce);
        var $spinner = $btn.next('.spinner');

        $btn.prop('disabled', true);
        $spinner.addClass('is-active');

        $.post(ajaxurl, {
            action: 'tmwseo_generate_title_suggestions',
            post_id: postId,
            nonce: nonce
        }).done(function (response) {
            if (response.success) {
                var html = '';
                $.each(response.data.titles, function (index, title) {
                    html += '<label style="display:block;margin:5px 0;">';
                    html += '<input type="radio" name="tmwseo_title_choice" value="' + index + '"> ';
                    html += $('<div>').text(title).html();
                    html += '</label>';
                });
                $('.tmwseo-suggestions-list').html(html);
                $('.tmwseo-title-suggestions').show();
            } else {
                alert('Error: ' + (response.data && response.data.message ? response.data.message : 'Request failed'));
            }
        }).fail(function () {
            alert('Error: request failed');
        }).always(function () {
            $spinner.removeClass('is-active');
            $btn.prop('disabled', false);
        });
    });

    $('#tmwseo-apply-title').on('click', function () {
        var postId = $(this).data('post-id') || $('#post_ID').val();
        var choice = $('input[name="tmwseo_title_choice"]:checked').val();
        var customTitle = $('#tmwseo-custom-title').val();

        if (typeof choice === 'undefined') {
            alert('Please select a title');
            return;
        }

        var selection = (choice === 'custom') ? customTitle : parseInt(choice, 10);

        $.post(ajaxurl, {
            action: 'tmwseo_apply_video_title',
            post_id: postId,
            selection: selection,
            nonce: nonce
        }).done(function (response) {
            if (response.success) {
                $('#title').val(response.data.new_title);
                alert('Title updated!');
            } else {
                alert('Error: ' + (response.data && response.data.message ? response.data.message : 'Request failed'));
            }
        }).fail(function () {
            alert('Error: request failed');
        });
    });
});
