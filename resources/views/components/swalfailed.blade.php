@if($message = Session::get('failed'))
    <script>
        Swal.fire({
            text: "{{ $message }}",
            icon: "error",
            buttonsStyling: false,
            confirmButtonText: "Ok, got it!",
            customClass: {
                confirmButton: "btn btn-danger"
            }
        });
    </script>
@endif
