let loading = {
    block: function () {
        $('#loading #loading-message').html(`
            <div class="bg-white py-3 px-5 rounded d-flex align-items-center">
                <span class="spinner-border text-primary" role="status" aria-hidden="true"></span>
                <span class="ms-2 fw-semibold">Loading...</span>
            </div>
        `);
        $('#loading').css('display', '');
        // matikan fungsi scroll
        // $('body').css('overflow', 'hidden');

        // window.onbeforeunload = function (event) {
        //     if (loading.isBlocked) {
        //         event.preventDefault();
        //         return event.returnValue = "Apakah anda yakin refresh halaman ?, merefresh halaman saat loading dapat mengagalkan prosess !";
        //     }
        // }

    },
    release: function () {
        $('#loading #loading-message').html('');
        $('#loading').css('display', 'none');
        // aktifkan fungsi scroll
        // $('body').css('overflow', 'scroll');
    },
    isBlocked: function () {
        return $('#loading').css('display') == 'none' ? false : true;
    }
};


