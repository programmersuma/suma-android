<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="styles.css">
        <title>Centered Logo</title>
    </head>
    <body>
        <div class="container">
            <div class="logo"></div>
        </div>
    </body>
</html>
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
		<link rel="canonical" href="https://suma-honda.id" />
		<link rel="shortcut icon" href="{{ asset('assets/images/logo/ic_suma.png') }}" />

		<!--begin::Fonts-->
		<link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700" />

		<!--begin::Page Vendor Stylesheets(used by this page)-->
		<link href="{{ asset('assets/plugins/custom/fullcalendar/fullcalendar.bundle.css') }}" rel="stylesheet" type="text/css" />
		<link href="{{ asset('assets/plugins/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" />

		<!--begin::Global Stylesheets Bundle(used by all pages)-->
		<link href="{{ asset('assets/plugins/global/plugins.bundle.css') }}" rel="stylesheet" type="text/css" />
		<link href="{{ asset('assets/css/style.bundle.css') }}" rel="stylesheet" type="text/css" />


	</head>

	<!--begin::Body-->
	<body id="kt_body" class="bg-body">
		<div class="d-flex flex-column flex-root">
			<div class="d-flex flex-column flex-column-fluid bgi-position-y-bottom position-x-center bgi-no-repeat bgi-size-contain bgi-attachment-fixed" style="background-image: url(assets/media/illustrations/sketchy-1/14.png">
				<div class="d-flex flex-center flex-column flex-column-fluid p-10 pb-lg-20">
					<div class="mb-2">
						<img alt="Logo" src="{{ asset('assets/images/logo/logo_suma_bg_white.svg') }}" class="h-150px" />
                    </div>

					<div class="w-lg-500px bg-body rounded shadow-sm p-10 p-lg-15 mx-auto">
						<form id="formLogin" class="form w-100">
                            <div class="text-center mb-10">
								<h1 class="text-dark mb-10">Suma Honda | PMO</h1>
                                <div class="text-center mb-10">
                                    <img alt="Logo" src="{{ asset('assets/images/logo/email.png') }}" class="h-150px" />
                                </div>
                                <div class="text-gray-400 fw-bold fs-4 mb-1">Forgot password</div>
                                <div class="text-gray-400 fw-bold fs-4 mb-2">Suma Parts Mobile Ordering</div>
							</div>
                            <div class="ms-5 mb-10">
                                <div class="fv-row mt-10">
                                     <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-4">
                                        <thead>
                                            <tr class="fs-7 fw-bolder text-muted bg-light">
                                                <th class="p-0 w-75px"></th>
                                                <th class="p-0 w-40px"></th>
                                                <th class="p-0 min-w-120px"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {{-- @foreach($users as $data)

                                            @endforeach --}}
                                            <tr>
                                                <td colspan="3" class="ps-3 pe-3" style="text-align:left;vertical-align:center;">
                                                    {{-- <span class="text-danger fw-boldest fs-4">DIVISI {{ strtoupper(trim($data->divisi)) }}</span> --}}
                                                    <span class="text-danger fw-boldest fs-4">DIVISI HONDA</span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="ps-3 pe-3" style="text-align:left;vertical-align:center;">
                                                    <span class="text-gray-400 fw-bolder fs-6">Email</span>
                                                </td>
                                                <td class="ps-3 pe-3" style="text-align:left;vertical-align:center;">
                                                    <span class="text-gray-400 fw-bolder fs-6">:</span>
                                                </td>
                                                <td class="ps-3 pe-3" style="text-align:left;vertical-align:center;">
                                                    {{-- <span class="text-dark fw-bolder text-hover-primary fs-6">{{ trim($data->email) }}</span> --}}
                                                    <span class="text-dark fw-bolder text-hover-primary fs-6">adityahendrawan1031@gmail.com</span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="ps-3 pe-3" style="text-align:left;vertical-align:center;">
                                                    <span class="text-gray-400 fw-bolder fs-6">User ID</span>
                                                </td>
                                                <td class="ps-3 pe-3" style="text-align:left;vertical-align:center;">
                                                    <span class="text-gray-400 fw-bolder fs-6">:</span>
                                                </td>
                                                <td class="ps-3 pe-3" style="text-align:left;vertical-align:center;">
                                                    {{-- <span class="text-dark fw-bolder text-hover-primary fs-6">{{ strtoupper(trim($data->user_id)) }}</span> --}}
                                                    <span class="text-dark fw-bolder text-hover-primary fs-6">ADITYA.H</span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="ps-3 pe-3" style="text-align:left;vertical-align:center;">
                                                    <span class="text-gray-400 fw-bolder fs-6">Role</span>
                                                </td>
                                                <td class="ps-3 pe-3" style="text-align:left;vertical-align:center;">
                                                    <span class="text-gray-400 fw-bolder fs-6">:</span>
                                                </td>
                                                <td class="ps-3 pe-3" style="text-align:left;vertical-align:center;">
                                                    {{-- <span class="text-dark fw-bolder text-hover-primary fs-6">{{ strtoupper(trim($data->role_id)) }}</span> --}}
                                                    <span class="text-dark fw-bolder text-hover-primary fs-6">MD_H3_MGMT</span>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="text-center mb-10">
                                <div class="text-gray-400 fw-bold fs-4 mb-4">New Password :</div>
								{{-- <h1 class="text-danger text-hover-primary fw-boldest fs-1 mb-10">{{ trim($new_password) }}</h1> --}}
								<h1 class="text-danger text-hover-primary fw-boldest fs-1 mb-10">123456</h1>
							</div>
						</form>
					</div>
				</div>

				<div class="d-flex flex-center flex-column-auto">
					<div class="d-flex align-items-center fw-bold fs-6">
						<a href="https://www.suma-honda.com/" class="btn btn-link btn-color-muted btn-active-color-primary me-5 mb-2">
                            <img alt="Logo" src="{{ asset('assets/images/logo/logo_suma_bg_white.svg') }}" class="h-20px me-3" />About
                        </a>
                        <a href="https://www.instagram.com/sumahonda/" class="btn btn-link btn-color-muted btn-active-color-primary me-5 mb-2">
                            <img alt="Logo" src="{{ asset('assets/images/logo/instagram.png') }}" class="h-20px me-3" />Instagram
                        </a>
                        <a href="https://www.tokopedia.com/sumahonda" class="btn btn-link btn-color-muted btn-active-color-primary me-5 mb-2">
                            <img alt="Logo" src="{{ asset('assets/images/logo/tokopedia.png') }}" class="h-20px me-3" />Tokopedia
                        </a>
                        <a href="https://shopee.co.id/sumahonda" class="btn btn-link btn-color-muted btn-active-color-primary me-5 mb-2">
                            <img alt="Logo" src="{{ asset('assets/images/logo/shopee.png') }}" class="h-20px me-3" />Shopee
                        </a>
                        <a href="https://www.tiktok.com/@sumahonda" class="btn btn-link btn-color-muted btn-active-color-primary me-5 mb-2">
                            <img alt="Logo" src="{{ asset('assets/images/logo/tiktok.png') }}" class="h-20px me-3" />Tiktok
                        </a>
					</div>
				</div>
			</div>
		</div>

		<script src="{{ URL::asset('assets/plugins/global/plugins.bundle.js') }}"></script>
		<script src="{{ URL::asset('assets/js/scripts.bundle.js') }}"></script>
	</body>
</html>
