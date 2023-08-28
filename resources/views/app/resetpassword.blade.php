<!--TESTING CHANGE-->
<!DOCTYPE html>
<html lang="en">
	<head>
		<base href="">
		<title>{{ env('APP_NAME') }}</title>
		<meta charset="utf-8" />
		<meta name="description" content="Program aplikasi member PT. Kharisma Suma Jaya Sakti divisi Honda" />
		<meta name="keywords" content="Sparepart Motor Honda, PT. Kharisma Suma Jaya Sakti, Suma Honda, Suma, Honda" />
        <meta name="viewport" content='width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0' />
		<meta property="og:locale" content="en_US" />
		<meta property="og:type" content="article" />
		<meta property="og:title" content="{{ env('APP_NAME') }}" />
		<meta property="og:url" content="{{ env('APP_URL') }}" />
		<meta property="og:site_name" content="Suma | Honda" />
		<link rel="shortcut icon" href="{{ asset('assets/images/logo/ic_suma.png') }}" />

		<!--begin::Fonts-->
		<link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700" />

		<!--begin::Page Vendor Stylesheets(used by this page)-->
		<link href="{{ asset('assets/plugins/custom/fullcalendar/fullcalendar.bundle.css') }}" rel="stylesheet" type="text/css" />
		<link href="{{ asset('assets/plugins/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" />

		<!--begin::Global Stylesheets Bundle(used by all pages)-->
		<link href="{{ asset('assets/plugins/global/plugins.bundle.css') }}" rel="stylesheet" type="text/css" />
		<link href="{{ asset('assets/css/style.bundle.css') }}" rel="stylesheet" type="text/css" />

        <!-- PWA  -->
        <meta name="theme-color" content="#ffffff"/>
        <link rel="apple-touch-icon" href="{{ asset('assets/images/logo/ic_suma.png') }}">
        <link rel="manifest" href="{{ asset('/manifest.json') }}">
	</head>

	<!--begin::Body-->
	<body id="kt_body" class="bg-body">
		<div class="d-flex flex-column flex-root">
			<div class="d-flex flex-column flex-column-fluid bgi-position-y-bottom position-x-center bgi-no-repeat bgi-size-contain bgi-attachment-fixed" style="background-image: url(assets/media/illustrations/sketchy-1/14.png">
				<div class="d-flex flex-center flex-column flex-column-fluid p-10 pb-lg-20">
					<a href="#" class="mb-2">
						<img alt="Logo" src="{{ asset('assets/images/logo/logo_suma_bg_white.svg') }}" class="h-150px" />
					</a>
					<div class="w-lg-500px bg-body rounded shadow-sm p-10 p-lg-15 mx-auto">
                        @if((int)$status->access == 1 && (int)$status->reset == 1)
                        <form id="formLogin" class="form w-100" novalidate="novalidate" id="formLogin" method="POST" action="{{ route('auth.submit-reset-password') }}">
                            {{ csrf_field() }}
                            <div class="text-center mb-10">
                                <h1 class="text-dark mb-3">Reset Password</h1>
                                <div class="text-gray-400 fw-bold fs-4">Inputkan kata sandi atau password baru anda</div>
                            </div>
                            <div class="fv-row mb-10">
                                <label class="form-label fs-6 fw-bolder text-dark">Email</label>
                                <input class="form-control form-control-lg form-control-solid" type="text" name="email" autocomplete="off"
                                    value="{{ trim($email) }}" readonly/>
                            </div>
                            <div class="fv-row mb-10">
                                <div class="d-flex flex-stack mb-2">
                                    <label class="form-label fw-bolder text-dark fs-6 mb-0">New Password</label>
                                </div>
                                <input class="form-control form-control-lg form-control" type="password" name="password" autocomplete="off"
                                    placeholder="-Inputkan password baru-" />
                            </div>
                            <div class="fv-row mb-10">
                                <div class="d-flex flex-stack mb-2">
                                    <label class="form-label fw-bolder text-dark fs-6 mb-0">Verify Password</label>
                                </div>
                                <input class="form-control form-control-lg form-control" type="password" name="password_verify" autocomplete="off"
                                    placeholder="-Ulangi password baru-" />
                            </div>
                            <div class="text-center">
                                <button type="submit" id="kt_sign_in_submit" class="btn btn-lg btn-primary w-100 mb-5">
                                    <span class="indicator-label">Reset Password</span>
                                    <span class="indicator-progress">Please wait...
                                    <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                                </button>
                            </div>
                        </form>
                        @else
                        <div class="text-center mb-10">
                            <h1 class="text-dark mb-3">Reset Password</h1>
                            <div class="text-gray-400 fw-bold fs-4 mt-20 mb-20">Sesi reset password anda sudah berakhir</div>
                        </div>
                        @endif
					</div>
				</div>

				<div class="d-flex flex-center flex-column-auto">
					<div class="d-flex align-items-center fw-bold fs-6">
						<a href="http://www.suma-honda.com/" class="text-muted text-hover-primary px-2">
                            <img alt="Logo" src="{{ asset('assets/images/logo/bg_logo_suma.png') }}" class="h-20px me-3" />About
                        </a>
						<a href="https://www.instagram.com/sumahonda/" class="text-muted text-hover-primary px-2">
                            <img alt="Logo" src="{{ asset('assets/images/logo/instagram.png') }}" class="h-20px me-3" />Instagram
                        </a>
                        <a href="https://www.tokopedia.com/sumahonda" class="text-muted text-hover-primary px-2">
                            <img alt="Logo" src="{{ asset('assets/images/logo/tokopedia.png') }}" class="h-20px me-3" />Tokopedia
                        </a>
                        <a href="https://shopee.co.id/sumahonda" class="text-muted text-hover-primary px-2">
                            <img alt="Logo" src="{{ asset('assets/images/logo/shopee.png') }}" class="h-20px me-3" />Shopee
                        </a>
                        <a href="https://www.tiktok.com/@sumahonda" class="text-muted text-hover-primary px-2">
                            <img alt="Logo" src="{{ asset('assets/images/logo/tiktok.png') }}" class="h-20px me-3" />Tiktok
                        </a>
					</div>
				</div>
			</div>
		</div>

		<script src="{{ URL::asset('assets/plugins/global/plugins.bundle.js') }}"></script>
		<script src="{{ URL::asset('assets/js/scripts.bundle.js') }}"></script>

        @extends('components.swalfailed')
        @extends('components.swalsuccess')
	</body>
</html>
