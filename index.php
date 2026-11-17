<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EV ChargeOS - Hệ thống Quản lý Trạm sạc Thông minh</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; }
        .tab-active { background-color: #ecfdf5; color: #059669; border-right: 3px solid #059669; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased">

    <div id="toast-container" class="fixed bottom-5 right-5 z-50 flex flex-col gap-2"></div>

    <!-- MÀN HÌNH ĐĂNG NHẬP / ĐĂNG KÝ BẮT BUỘC -->
    <div id="auth-screen" class="min-h-screen flex items-center justify-center bg-gradient-to-br from-slate-900 via-slate-800 to-emerald-950 p-4">
        <div class="bg-white w-full max-w-md rounded-3xl shadow-2xl overflow-hidden border border-slate-200">
            <div class="bg-emerald-600 p-6 text-white text-center">
                <div class="w-12 h-12 rounded-2xl bg-white/20 mx-auto flex items-center justify-center mb-3">
                    <i data-lucide="zap" class="w-7 h-7"></i>
                </div>
                <h1 class="text-xl font-bold">EV ChargeOS</h1>
                <p class="text-xs text-emerald-100 mt-1">Hệ thống Xác thực Quản trị viên & Tài xế EV</p>
            </div>

            <div class="p-6 space-y-5">
                <div class="grid grid-cols-2 gap-2 bg-slate-100 p-1.5 rounded-xl">
                    <button type="button" id="auth-tab-customer" onclick="setAuthTab('customer')"
                        class="py-2 rounded-lg text-xs font-bold transition bg-white text-emerald-700 shadow-sm">
                        Khách hàng (Tài xế)
                    </button>
                    <button type="button" id="auth-tab-admin" onclick="setAuthTab('admin')"
                        class="py-2 rounded-lg text-xs font-bold transition text-slate-600 hover:text-slate-900">
                        Quản trị viên (Admin)
                    </button>
                </div>

                <!-- Form Đăng nhập -->
                <form id="login-form" onsubmit="handleLogin(event)" class="space-y-4">
                    <div>
                        <label id="login-user-label" class="block text-xs font-semibold text-slate-700 mb-1">Tên đăng nhập hoặc Số điện thoại</label>
                        <input type="text" id="login-username" placeholder="VD: 0901234567" required
                            class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm outline-none focus:border-emerald-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Mật khẩu</label>
                        <input type="password" id="login-password" placeholder="••••••" required
                            class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm outline-none focus:border-emerald-500">
                    </div>

                    <p id="login-error-msg" class="hidden text-xs text-rose-600 bg-rose-50 border border-rose-200 p-2.5 rounded-xl"></p>

                    <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2.5 rounded-xl text-sm transition shadow-sm">
                        Đăng nhập hệ thống
                    </button>

        

                    <div id="register-toggle-wrap" class="text-center pt-1">
                        <button type="button" onclick="toggleRegisterMode(true)" class="text-xs font-semibold text-emerald-600 hover:underline">
                            Chưa có tài khoản tài xế? Đăng ký ngay →
                        </button>
                    </div>
                </form>

                <!-- Form Đăng ký nhanh cho Khách hàng -->
                <form id="register-form" onsubmit="handleRegister(event)" class="hidden space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Họ và tên *</label>
                        <input type="text" id="reg-name" placeholder="Nguyễn Văn A" required class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Số điện thoại (10 số, bắt đầu 0) *</label>
                            <input type="text" id="reg-phone" placeholder="0909123456" maxlength="10" pattern="0[0-9]{9}"
                                title="Số điện thoại phải bắt đầu bằng số 0 và có đúng 10 chữ số" required
                                class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Mật khẩu *</label>
                            <input type="password" id="reg-pass" placeholder="••••••" required class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Biển số xe (tùy chọn)</label>
                            <input type="text" id="reg-plate" placeholder="51K-999.99" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Dòng xe EV</label>
                            <input type="text" id="reg-model" placeholder="VinFast VF 8" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                        </div>
                    </div>
                    <p id="reg-error-msg" class="hidden text-xs text-rose-600 bg-rose-50 border border-rose-200 p-2 rounded-xl"></p>
                    <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2.5 rounded-xl text-sm transition">
                        Tạo tài khoản & Đăng nhập
                    </button>
                    <div class="text-center">
                        <button type="button" onclick="toggleRegisterMode(false)" class="text-xs text-slate-500 hover:underline">
                            ← Quay lại đăng nhập
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- GIAO DIỆN CHÍNH SAU KHI ĐĂNG NHẬP -->
    <div id="app-shell" class="hidden min-h-screen flex flex-col md:flex-row">
        <!-- Sidebar (Admin) -->
        <aside id="admin-sidebar" class="w-full md:w-64 bg-white border-r border-slate-200 flex-shrink-0 flex flex-col justify-between">
            <div>
                <div class="p-6 border-b border-slate-100 flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-emerald-600 text-white flex items-center justify-center shadow-sm">
                        <i data-lucide="zap" class="w-6 h-6"></i>
                    </div>
                    <div>
                        <h1 class="font-bold text-slate-900 text-lg leading-tight">EV ChargeOS</h1>
                        <p class="text-xs text-slate-500">Admin Control Panel</p>
                    </div>
                </div>

                <nav class="p-3 space-y-1">
                    <button onclick="switchTab('dashboard')" id="nav-dashboard" class="nav-btn tab-active w-full flex items-center gap-3 px-4 py-3 text-sm font-medium rounded-lg transition">
                        <i data-lucide="layout-dashboard" class="w-4 h-4"></i> Tổng quan (Dashboard)
                    </button>
                    <button onclick="switchTab('stations')" id="nav-stations" class="nav-btn w-full flex items-center gap-3 px-4 py-3 text-sm font-medium text-slate-600 hover:bg-slate-50 rounded-lg transition">
                        <i data-lucide="map-pin" class="w-4 h-4"></i> Trạm & Cổng sạc
                    </button>
                    <button onclick="switchTab('reservations')" id="nav-reservations" class="nav-btn w-full flex items-center gap-3 px-4 py-3 text-sm font-medium text-slate-600 hover:bg-slate-50 rounded-lg transition">
                        <i data-lucide="calendar-clock" class="w-4 h-4"></i> Quản lý Đặt chỗ
                    </button>
                    <button onclick="switchTab('sessions')" id="nav-sessions" class="nav-btn w-full flex items-center gap-3 px-4 py-3 text-sm font-medium text-slate-600 hover:bg-slate-50 rounded-lg transition">
                        <i data-lucide="battery-charging" class="w-4 h-4"></i> Phiên sạc & Hóa đơn
                    </button>
                    <button onclick="switchTab('users')" id="nav-users" class="nav-btn w-full flex items-center justify-between px-4 py-3 text-sm font-medium text-slate-600 hover:bg-slate-50 rounded-lg transition">
                        <span class="flex items-center gap-3"><i data-lucide="users" class="w-4 h-4"></i> Khách hàng & Nạp tiền</span>
                        <span id="pending-topup-badge" class="hidden px-2 py-0.5 text-[11px] font-bold rounded-full bg-amber-100 text-amber-800">0</span>
                    </button>
                </nav>
            </div>

            <div class="p-4 m-3 rounded-xl bg-slate-50 border border-slate-200/80 text-xs text-slate-500 space-y-1">
                <div class="flex items-center justify-between font-medium text-slate-700">
                    <span>Trạng thái MySQL</span>
                    <span id="db-status-badge" class="inline-flex items-center gap-1 text-emerald-600">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span> Online
                    </span>
                </div>
                <p id="live-clock" class="font-mono text-slate-600 pt-1">--:--:--</p>
            </div>
        </aside>

        <!-- Main Workspace -->
        <div class="flex-1 flex flex-col min-w-0">
            <header class="bg-white border-b border-slate-200 px-6 py-4 flex flex-wrap items-center justify-between gap-4 sticky top-0 z-30">
                <div>
                    <h2 id="page-title" class="text-lg font-bold text-slate-900">Tổng quan Vận hành</h2>
                    <p id="page-subtitle" class="text-xs text-slate-500">Giám sát thời gian thực hệ thống trạm sạc Smart City</p>
                </div>

                <div class="flex items-center gap-3">
                    <div class="flex items-center gap-2 bg-slate-100 px-3.5 py-2 rounded-xl border border-slate-200">
                        <span id="logged-role-badge" class="px-2 py-0.5 rounded-md text-[11px] font-bold bg-emerald-600 text-white">ADMIN</span>
                        <span id="logged-user-name" class="text-xs font-bold text-slate-800">--</span>
                    </div>
                    <button onclick="handleLogout()"
                        class="px-3.5 py-2 rounded-xl text-xs font-semibold bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 transition flex items-center gap-1.5">
                        <i data-lucide="log-out" class="w-3.5 h-3.5"></i> Đăng xuất
                    </button>
                </div>
            </header>

            <main class="p-6 flex-1 space-y-6 max-w-7xl w-full mx-auto">

                <!-- VIEW 0: CUSTOMER PORTAL -->
                <section id="view-customer-portal" class="hidden space-y-6">
                    <!-- Banner Khách hàng -->
                    <div class="bg-gradient-to-r from-emerald-600 to-teal-700 rounded-2xl p-6 text-white flex flex-wrap justify-between items-center gap-4 shadow-sm">
                        <div>
                            <span class="inline-block px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-white/20 mb-2">Cổng thông tin Tài xế EV</span>
                            <h2 id="cust-welcome-name" class="text-2xl font-bold">--</h2>
                            <p id="cust-tier-info" class="text-xs text-emerald-100 mt-1">--</p>
                        </div>
                        <div class="bg-white/15 backdrop-blur-sm px-5 py-3.5 rounded-xl border border-white/25 text-right">
                            <p class="text-xs text-emerald-100">Số dư ví sạc điện tử</p>
                            <p id="cust-wallet-balance" class="text-2xl font-bold mt-0.5">0 đ</p>
                        </div>
                    </div>

                    <!-- Khối Cập Nhật Số Điện Thoại Cá Nhân -->
                    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                        <div class="flex flex-wrap justify-between items-center gap-4">
                            <div>
                                <h3 class="font-bold text-slate-800 flex items-center gap-2">
                                    <i data-lucide="phone" class="w-4 h-4 text-emerald-600"></i> Cập nhật Số điện thoại tài khoản
                                </h3>
                                <p class="text-xs text-slate-500 mt-0.5">Quy định: Số điện thoại bắt đầu từ số <b>0</b> và có tổng cộng <b>10 chữ số</b> (tính cả số 0).</p>
                            </div>
                            <form onsubmit="handleUpdatePhone(event)" class="flex flex-wrap items-center gap-2">
                                <input type="text" id="cust-new-phone" maxlength="10" pattern="0[0-9]{9}"
                                    placeholder="Nhập SĐT 10 số (VD: 0912345678)" required
                                    class="rounded-xl border border-slate-300 px-3.5 py-2 text-sm font-mono w-60 outline-none focus:border-emerald-500">
                                <button type="submit" class="bg-slate-900 hover:bg-emerald-600 text-white font-semibold px-4 py-2 rounded-xl text-xs transition">
                                    Lưu SĐT mới
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Nạp tiền vào ví & Lịch sử yêu cầu nạp -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm space-y-4">
                            <h3 class="font-bold text-slate-800 flex items-center gap-2">
                                <i data-lucide="wallet" class="w-4 h-4 text-emerald-600"></i> Nạp tiền vào ví sạc điện tử
                            </h3>
                            <div class="flex flex-wrap gap-2">
                                <button type="button" onclick="setTopupAmount(100000)" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-slate-100 hover:bg-emerald-50 hover:text-emerald-700 border border-slate-200 transition">100.000 đ</button>
                                <button type="button" onclick="setTopupAmount(200000)" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-slate-100 hover:bg-emerald-50 hover:text-emerald-700 border border-slate-200 transition">200.000 đ</button>
                                <button type="button" onclick="setTopupAmount(500000)" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-slate-100 hover:bg-emerald-50 hover:text-emerald-700 border border-slate-200 transition">500.000 đ</button>
                                <button type="button" onclick="setTopupAmount(1000000)" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-slate-100 hover:bg-emerald-50 hover:text-emerald-700 border border-slate-200 transition">1.000.000 đ</button>
                            </div>
                            <form onsubmit="handleCustomerTopup(event)" class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">
                                <div>
                                    <label class="block text-xs font-medium text-slate-600 mb-1">Số tiền muốn nạp (VNĐ)</label>
                                    <input type="number" id="cust-topup-amount" value="200000" min="10000" step="10000" required
                                        class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-600 mb-1">Phương thức</label>
                                    <select id="cust-topup-method" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm bg-slate-50">
                                        <option value="Chuyển khoản QR">Chuyển khoản QR</option>
                                        <option value="Ví MoMo">Ví MoMo</option>
                                        <option value="Thẻ nội địa / ATM">Thẻ nội địa / ATM</option>
                                    </select>
                                </div>
                                <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2 px-4 rounded-xl text-sm transition shadow-sm">
                                    Gửi yêu cầu nạp
                                </button>
                            </form>
                            <p class="text-[11px] text-slate-500">* Sau khi gửi, Quản trị viên sẽ đối soát và xác nhận cộng tiền trực tiếp vào ví của bạn.</p>
                        </div>

                        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                            <h3 class="font-bold text-slate-800 mb-3 flex items-center gap-2">
                                <i data-lucide="history" class="w-4 h-4 text-blue-600"></i> Trạng thái yêu cầu nạp tiền của tôi
                            </h3>
                            <div id="cust-topups-list" class="overflow-x-auto max-h-48"></div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                            <h3 class="font-bold text-slate-800 mb-3 flex items-center gap-2">
                                <i data-lucide="car" class="w-4 h-4 text-emerald-600"></i> Phương tiện của tôi
                            </h3>
                            <div id="cust-vehicles-list" class="space-y-3"></div>
                        </div>

                        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                            <h3 class="font-bold text-slate-800 mb-3 flex items-center gap-2">
                                <i data-lucide="zap" class="w-4 h-4 text-amber-500"></i> Trạng thái sạc hiện tại
                            </h3>
                            <div id="cust-active-session"></div>
                        </div>
                    </div>

                    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                        <h3 class="font-bold text-slate-800 mb-4 flex items-center gap-2">
                            <i data-lucide="calendar-plus" class="w-4 h-4 text-emerald-600"></i> Đặt trước cổng sạc nhanh
                        </h3>
                        <form onsubmit="handleCustomerReservation(event)" class="grid grid-cols-1 md:grid-cols-5 gap-3 items-end">
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">Xe của tôi</label>
                                <select id="cust-res-vehicle" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm bg-slate-50"></select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">Chọn Trạm & Cổng</label>
                                <select id="cust-res-port" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm bg-slate-50"></select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">Giờ bắt đầu</label>
                                <input type="time" id="cust-res-start" value="14:00" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">Giờ kết thúc</label>
                                <input type="time" id="cust-res-end" value="15:30" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                            </div>
                            <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2 px-4 rounded-xl text-sm transition shadow-sm">
                                Xác nhận đặt chỗ
                            </button>
                        </form>
                        <p id="cust-res-error" class="hidden mt-3 text-xs text-rose-600 bg-rose-50 border border-rose-200 p-2.5 rounded-lg"></p>
                    </div>

                    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                        <h3 class="font-bold text-slate-800 mb-3">Lịch đặt chỗ của tôi</h3>
                        <div id="cust-reservations-list" class="overflow-x-auto"></div>
                    </div>

                    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                        <h3 class="font-bold text-slate-800 mb-3">Lịch sử sạc & Hóa đơn cá nhân</h3>
                        <div id="cust-sessions-list" class="overflow-x-auto"></div>
                    </div>
                </section>

                <!-- VIEW 1: ADMIN DASHBOARD -->
                <section id="view-dashboard" class="space-y-6">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="text-xs font-medium text-slate-500">Tổng số Trạm / Cổng</p>
                                    <h3 id="kpi-stations" class="text-2xl font-bold text-slate-900 mt-1">0 / 0</h3>
                                </div>
                                <span class="p-2.5 bg-emerald-50 text-emerald-600 rounded-xl"><i data-lucide="map-pin" class="w-5 h-5"></i></span>
                            </div>
                            <p id="kpi-available-ports" class="text-xs text-emerald-600 font-medium mt-3">0 cổng đang sẵn sàng</p>
                        </div>

                        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="text-xs font-medium text-slate-500">Đang sạc trực tiếp</p>
                                    <h3 id="kpi-charging" class="text-2xl font-bold text-slate-900 mt-1">0 xe</h3>
                                </div>
                                <span class="p-2.5 bg-amber-50 text-amber-600 rounded-xl"><i data-lucide="zap" class="w-5 h-5"></i></span>
                            </div>
                            <p class="text-xs text-slate-500 mt-3">Giám sát tải thời gian thực</p>
                        </div>

                        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="text-xs font-medium text-slate-500">Sản lượng điện (kWh)</p>
                                    <h3 id="kpi-energy" class="text-2xl font-bold text-slate-900 mt-1">0 kWh</h3>
                                </div>
                                <span class="p-2.5 bg-blue-50 text-blue-600 rounded-xl"><i data-lucide="battery-charging" class="w-5 h-5"></i></span>
                            </div>
                            <p class="text-xs text-blue-600 font-medium mt-3">Tổng hợp từ các phiên sạc</p>
                        </div>

                        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="text-xs font-medium text-slate-500">Doanh thu ước tính</p>
                                    <h3 id="kpi-revenue" class="text-2xl font-bold text-slate-900 mt-1">0 đ</h3>
                                </div>
                                <span class="p-2.5 bg-purple-50 text-purple-600 rounded-xl"><i data-lucide="wallet" class="w-5 h-5"></i></span>
                            </div>
                            <p class="text-xs text-slate-500 mt-3">Đơn giá trung bình 3.850 đ/kWh</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <div class="lg:col-span-2 bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                            <h3 class="font-bold text-slate-800 mb-4">Biểu đồ Công suất tiêu thụ theo khung giờ (kW)</h3>
                            <div class="h-64">
                                <canvas id="powerChart"></canvas>
                            </div>
                        </div>

                        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-between">
                            <div>
                                <h3 class="font-bold text-slate-800 mb-3">Tình trạng Cổng sạc</h3>
                                <div id="dashboard-port-summary" class="space-y-3"></div>
                            </div>
                            <button onclick="switchTab('stations')" class="mt-4 w-full py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs rounded-xl transition">
                                Xem chi tiết sơ đồ cổng sạc →
                            </button>
                        </div>
                    </div>
                </section>

                <!-- VIEW 2: STATIONS & PORTS -->
                <section id="view-stations" class="hidden space-y-6">
                    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                        <h3 class="font-bold text-slate-800 mb-3 flex items-center gap-2">
                            <i data-lucide="plus-circle" class="w-4 h-4 text-emerald-600"></i> Thêm Cổng sạc mới vào Trạm
                        </h3>
                        <form onsubmit="handleCreatePort(event)" class="grid grid-cols-1 md:grid-cols-5 gap-3 items-end">
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">Thuộc Trạm sạc</label>
                                <select id="new-port-station" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm bg-slate-50"></select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">Mã số hiệu cổng (VD: A06)</label>
                                <input type="text" id="new-port-code" placeholder="A06" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">Chuẩn kết nối</label>
                                <select id="new-port-connector" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm bg-slate-50">
                                    <option value="CCS2">CCS2 (Sạc nhanh DC)</option>
                                    <option value="Type 2">Type 2 (Sạc tiêu chuẩn AC)</option>
                                    <option value="CHAdeMO">CHAdeMO</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">Công suất tối đa (kW)</label>
                                <input type="number" id="new-port-kw" value="120" min="7" max="350" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                            </div>
                            <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2 px-4 rounded-xl text-sm transition shadow-sm">
                                + Thêm cổng sạc
                            </button>
                        </form>
                    </div>

                    <div class="flex flex-wrap justify-between items-center gap-3">
                        <h3 class="font-bold text-slate-800">Danh sách Trạm sạc & Trạng thái từng Cổng</h3>
                        <select id="station-filter" onchange="renderStations()" class="rounded-xl border border-slate-200 px-3.5 py-2 text-sm bg-white">
                            <option value="all">Tất cả các trạm</option>
                        </select>
                    </div>
                    <div id="stations-grid" class="grid grid-cols-1 md:grid-cols-2 gap-6"></div>
                </section>

                <!-- VIEW 3: RESERVATIONS -->
                <section id="view-reservations" class="hidden space-y-6">
                    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                        <h3 class="font-bold text-slate-800 mb-4">Tạo Đặt chỗ mới</h3>
                        <form onsubmit="handleCreateReservation(event)" class="grid grid-cols-1 md:grid-cols-6 gap-3 items-end">
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">Khách hàng</label>
                                <select id="res-user" onchange="syncReservationVehicles()" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm bg-slate-50"></select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">Phương tiện</label>
                                <select id="res-vehicle" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm bg-slate-50"></select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">Cổng sạc</label>
                                <select id="res-port" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm bg-slate-50"></select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">Bắt đầu</label>
                                <input type="time" id="res-start" value="15:00" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">Kết thúc</label>
                                <input type="time" id="res-end" value="16:30" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                            </div>
                            <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2 px-4 rounded-xl text-sm transition">
                                + Lưu đặt chỗ
                            </button>
                        </form>
                        <p id="res-validation-msg" class="hidden mt-3 text-xs text-rose-600 bg-rose-50 border border-rose-200 p-2.5 rounded-lg"></p>
                    </div>

                    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm overflow-x-auto">
                        <h3 class="font-bold text-slate-800 mb-4">Danh sách Lịch đặt chỗ (Bảng DAT_CHO)</h3>
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="border-b border-slate-200 text-slate-500 text-xs">
                                    <th class="py-2.5">Mã ĐC</th>
                                    <th>Khách hàng</th>
                                    <th>Biển số</th>
                                    <th>Cổng sạc</th>
                                    <th>Khung giờ</th>
                                    <th>Trạng thái</th>
                                    <th class="text-right">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody id="reservations-table-body" class="divide-y divide-slate-100"></tbody>
                        </table>
                    </div>
                </section>

                <!-- VIEW 4: CHARGING SESSIONS -->
                <section id="view-sessions" class="hidden space-y-6">
                    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm overflow-x-auto">
                        <h3 class="font-bold text-slate-800 mb-4">Nhật ký Phiên sạc & Tính tiền (Bảng PHIEN_SAC)</h3>
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="border-b border-slate-200 text-slate-500 text-xs">
                                    <th class="py-2.5">Mã Phiên</th>
                                    <th>Biển số</th>
                                    <th>Cổng sạc</th>
                                    <th>Bắt đầu - Kết thúc</th>
                                    <th>Điện năng</th>
                                    <th>Thành tiền</th>
                                    <th>Trạng thái</th>
                                </tr>
                            </thead>
                            <tbody id="sessions-table-body" class="divide-y divide-slate-100"></tbody>
                        </table>
                    </div>
                </section>

                <!-- VIEW 5: USERS, TOPUPS & VEHICLES -->
                <section id="view-users" class="hidden space-y-6">
                    <!-- Bảng Xác Nhận Nạp Tiền & Tìm Tên Khách Hàng -->
                    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm space-y-4">
                        <div class="flex flex-wrap justify-between items-center gap-3">
                            <div>
                                <h3 class="font-bold text-slate-800 flex items-center gap-2">
                                    <i data-lucide="badge-dollar-sign" class="w-5 h-5 text-amber-500"></i> Xác nhận Yêu cầu Nạp tiền của Khách hàng
                                </h3>
                                <p class="text-xs text-slate-500">Tìm tên khách hàng và bấm xác nhận để cộng tiền vào ví trong MySQL</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <input type="text" id="admin-topup-search" oninput="renderAdminTopups()"
                                    placeholder="Tìm tên khách hàng / SĐT..."
                                    class="rounded-xl border border-slate-200 px-3.5 py-2 text-xs w-56 outline-none focus:border-emerald-500">
                                <select id="admin-topup-status-filter" onchange="renderAdminTopups()"
                                    class="rounded-xl border border-slate-200 px-3 py-2 text-xs bg-slate-50">
                                    <option value="all">Tất cả trạng thái</option>
                                    <option value="Pending">Chờ xác nhận (Pending)</option>
                                    <option value="Approved">Đã duyệt (Approved)</option>
                                    <option value="Rejected">Đã từ chối</option>
                                </select>
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead>
                                    <tr class="border-b border-slate-200 text-slate-500 text-xs">
                                        <th class="py-2.5">Mã GD</th>
                                        <th>Khách hàng</th>
                                        <th>Số điện thoại</th>
                                        <th>Số tiền nạp</th>
                                        <th>Phương thức</th>
                                        <th>Thời gian gửi</th>
                                        <th>Trạng thái</th>
                                        <th class="text-right">Xác nhận</th>
                                    </tr>
                                </thead>
                                <tbody id="admin-topups-table-body" class="divide-y divide-slate-100"></tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Form Thêm Khách Hàng Kèm Tài Khoản Đăng Nhập -->
                    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                        <h3 class="font-bold text-slate-800 mb-3 flex items-center gap-2">
                            <i data-lucide="user-plus" class="w-4 h-4 text-emerald-600"></i> Tạo Tài khoản Khách hàng & Phương tiện EV mới
                        </h3>
                        <form onsubmit="handleCreateUserVehicle(event)" class="grid grid-cols-1 md:grid-cols-6 gap-3 items-end">
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">Họ và tên *</label>
                                <input type="text" id="new-user-name" placeholder="Nguyễn Văn Mới" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">SĐT 10 số (Username) *</label>
                                <input type="text" id="new-user-phone" placeholder="0909123456" maxlength="10" pattern="0[0-9]{9}"
                                    title="Số điện thoại phải bắt đầu bằng số 0 và có đúng 10 chữ số" required
                                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">Mật khẩu đăng nhập *</label>
                                <input type="text" id="new-user-pass" value="123456" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">Biển số xe</label>
                                <input type="text" id="new-veh-plate" placeholder="51H-888.99" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">Dòng xe EV</label>
                                <input type="text" id="new-veh-model" placeholder="VinFast VF 9" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                            </div>
                            <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2 px-4 rounded-xl text-sm transition shadow-sm">
                                + Tạo khách hàng
                            </button>
                        </form>
                    </div>

                    <!-- Tìm kiếm & Danh sách Khách hàng + Phương tiện -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm overflow-x-auto space-y-3">
                            <div class="flex flex-wrap justify-between items-center gap-2">
                                <h3 class="font-bold text-slate-800">Danh sách Khách hàng (KHACH_HANG)</h3>
                                <input type="text" id="admin-user-search" oninput="renderUsersAndVehicles()"
                                    placeholder="Tìm tên, SĐT, username..."
                                    class="rounded-xl border border-slate-200 px-3 py-1.5 text-xs w-48 outline-none focus:border-emerald-500">
                            </div>
                            <table class="w-full text-left text-sm">
                                <thead>
                                    <tr class="border-b border-slate-200 text-slate-500 text-xs">
                                        <th class="py-2">ID</th>
                                        <th>Họ tên</th>
                                        <th>Username / SĐT</th>
                                        <th class="text-right">Số dư ví</th>
                                    </tr>
                                </thead>
                                <tbody id="users-table-body" class="divide-y divide-slate-100"></tbody>
                            </table>
                        </div>

                        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm overflow-x-auto space-y-3">
                            <div class="flex flex-wrap justify-between items-center gap-2">
                                <h3 class="font-bold text-slate-800">Phương tiện đăng ký (PHUONG_TIEN)</h3>
                                <input type="text" id="admin-vehicle-search" oninput="renderUsersAndVehicles()"
                                    placeholder="Tìm biển số, chủ xe..."
                                    class="rounded-xl border border-slate-200 px-3 py-1.5 text-xs w-48 outline-none focus:border-emerald-500">
                            </div>
                            <table class="w-full text-left text-sm">
                                <thead>
                                    <tr class="border-b border-slate-200 text-slate-500 text-xs">
                                        <th class="py-2">Biển số</th>
                                        <th>Dòng xe</th>
                                        <th>Pin</th>
                                        <th>Chủ sở hữu</th>
                                    </tr>
                                </thead>
                                <tbody id="vehicles-table-body" class="divide-y divide-slate-100"></tbody>
                            </table>
                        </div>
                    </div>
                </section>

            </main>
        </div>
    </div>

    <script>
        const TARIFF_RATE = 3850;
        let selectedAuthRole = 'customer';
        let currentAuth = null;
        let powerChartInstance = null;

        const state = {
            stations: [],
            ports: [],
            users: [],
            vehicles: [],
            reservations: [],
            sessions: [],
            topups: []
        };

        function showToast(message, type = 'success') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            const colors = type === 'error' ? 'bg-rose-600 text-white' : type === 'warning' ? 'bg-amber-500 text-white' : 'bg-slate-900 text-white';
            toast.className = `${colors} px-4 py-3 rounded-xl shadow-lg text-xs font-medium flex items-center gap-2 transition-all`;
            toast.textContent = message;
            container.appendChild(toast);
            setTimeout(() => toast.remove(), 4500);
        }

        function isValidVNPhone(phone) {
            return /^0\d{9}$/.test(phone);
        }

        // --- XỬ LÝ MÀN HÌNH ĐĂNG NHẬP / ĐĂNG KÝ ---
        function setAuthTab(role) {
            selectedAuthRole = role;
            toggleRegisterMode(false);
            const tabCust = document.getElementById('auth-tab-customer');
            const tabAdmin = document.getElementById('auth-tab-admin');
            const userLabel = document.getElementById('login-user-label');
            const userInput = document.getElementById('login-username');
            const hintText = document.getElementById('login-hint-text');
            const regWrap = document.getElementById('register-toggle-wrap');

            document.getElementById('login-error-msg').classList.add('hidden');

            if (role === 'admin') {
                tabAdmin.className = 'py-2 rounded-lg text-xs font-bold transition bg-slate-900 text-white shadow-sm';
                tabCust.className = 'py-2 rounded-lg text-xs font-bold transition text-slate-600 hover:text-slate-900';
                userLabel.textContent = 'Tên đăng nhập Quản trị viên (Username)';
                userInput.placeholder = 'admin';
                hintText.innerHTML = '• Tài khoản Admin: <code>admin</code> | Mật khẩu: <code>admin123</code>';
                regWrap.classList.add('hidden');
            } else {
                tabCust.className = 'py-2 rounded-lg text-xs font-bold transition bg-white text-emerald-700 shadow-sm';
                tabAdmin.className = 'py-2 rounded-lg text-xs font-bold transition text-slate-600 hover:text-slate-900';
                userLabel.textContent = 'Tên đăng nhập hoặc Số điện thoại';
                userInput.placeholder = 'VD: 0901234567';
                hintText.innerHTML = '• Khách hàng: <b>Số điện thoại</b> (VD: <code>0901234567</code>) | Mật khẩu: <code>123456</code>';
                regWrap.classList.remove('hidden');
            }
        }

        function toggleRegisterMode(showReg) {
            document.getElementById('login-form').classList.toggle('hidden', showReg);
            document.getElementById('register-form').classList.toggle('hidden', !showReg);
        }

        async function handleLogin(e) {
            e.preventDefault();
            const errBox = document.getElementById('login-error-msg');
            const payload = {
                role: selectedAuthRole,
                username: document.getElementById('login-username').value,
                password: document.getElementById('login-password').value
            };
            try {
                const res = await fetch('api.php?action=login', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (!data.success) {
                    errBox.textContent = data.message;
                    errBox.classList.remove('hidden');
                } else {
                    errBox.classList.add('hidden');
                    showToast(data.message);
                    await enterAppWithAuth(data.auth);
                }
            } catch (err) {
                errBox.textContent = 'Lỗi kết nối tới api.php!';
                errBox.classList.remove('hidden');
            }
        }

        async function handleRegister(e) {
            e.preventDefault();
            const errBox = document.getElementById('reg-error-msg');
            const phone = document.getElementById('reg-phone').value.trim();

            if (!isValidVNPhone(phone)) {
                errBox.textContent = 'Số điện thoại phải bắt đầu từ số 0 và có đúng 10 chữ số!';
                errBox.classList.remove('hidden');
                return;
            }

            const payload = {
                name: document.getElementById('reg-name').value,
                phone: phone,
                username: phone,
                password: document.getElementById('reg-pass').value,
                plate: document.getElementById('reg-plate').value,
                model: document.getElementById('reg-model').value || 'VinFast VF 8'
            };
            try {
                const res = await fetch('api.php?action=register_customer', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (!data.success) {
                    errBox.textContent = data.message;
                    errBox.classList.remove('hidden');
                } else {
                    errBox.classList.add('hidden');
                    showToast(data.message);
                    await enterAppWithAuth(data.auth);
                }
            } catch (err) {
                errBox.textContent = 'Lỗi đăng ký tài khoản!';
                errBox.classList.remove('hidden');
            }
        }

        async function handleLogout() {
            await fetch('api.php?action=logout');
            currentAuth = null;
            document.getElementById('app-shell').classList.add('hidden');
            document.getElementById('auth-screen').classList.remove('hidden');
            document.getElementById('login-password').value = '';
            showToast('Đã đăng xuất khỏi hệ thống.');
        }

        async function enterAppWithAuth(auth) {
            currentAuth = auth;
            document.getElementById('auth-screen').classList.add('hidden');
            document.getElementById('app-shell').classList.remove('hidden');

            const roleBadge = document.getElementById('logged-role-badge');
            document.getElementById('logged-user-name').textContent = `${auth.name} (@${auth.username})`;

            const sidebar = document.getElementById('admin-sidebar');
            const customerPortal = document.getElementById('view-customer-portal');

            if (auth.role === 'admin') {
                roleBadge.textContent = 'QUẢN TRỊ VIÊN';
                roleBadge.className = 'px-2 py-0.5 rounded-md text-[11px] font-bold bg-slate-900 text-white';
                sidebar.classList.remove('hidden');
                customerPortal.classList.add('hidden');
                if (!powerChartInstance) initChart();
                switchTab('dashboard');
            } else {
                roleBadge.textContent = 'KHÁCH HÀNG';
                roleBadge.className = 'px-2 py-0.5 rounded-md text-[11px] font-bold bg-emerald-600 text-white';
                sidebar.classList.add('hidden');
                document.querySelectorAll('main > section').forEach(sec => sec.classList.add('hidden'));
                customerPortal.classList.remove('hidden');
                document.getElementById('page-title').textContent = 'Cổng Khách hàng & Tài xế EV';
                document.getElementById('page-subtitle').textContent = 'Quản lý SĐT, nạp tiền vào ví và đặt lịch sạc nhanh';
            }

            await loadDataFromMySQL();
            lucide.createIcons();
        }

        // --- ĐỔI SỐ ĐIỆN THOẠI KHÁCH HÀNG ---
        async function handleUpdatePhone(e) {
            e.preventDefault();
            const phone = document.getElementById('cust-new-phone').value.trim();
            if (!isValidVNPhone(phone)) {
                showToast('Số điện thoại phải bắt đầu từ số 0 và có tổng cộng 10 chữ số!', 'error');
                return;
            }
            try {
                const res = await fetch('api.php?action=update_phone', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ phone })
                });
                const data = await res.json();
                if (data.success) {
                    if (data.auth) {
                        currentAuth = data.auth;
                        document.getElementById('logged-user-name').textContent = `${currentAuth.name} (@${currentAuth.username})`;
                    }
                    showToast(data.message);
                    await loadDataFromMySQL();
                } else {
                    showToast(data.message, 'error');
                }
            } catch (err) {
                showToast('Lỗi kết nối khi cập nhật số điện thoại.', 'error');
            }
        }

        // --- NẠP TIỀN & DUYỆT NẠP TIỀN ---
        function setTopupAmount(val) {
            document.getElementById('cust-topup-amount').value = val;
        }

        async function handleCustomerTopup(e) {
            e.preventDefault();
            const amount = Number(document.getElementById('cust-topup-amount').value);
            const method = document.getElementById('cust-topup-method').value;
            try {
                const res = await fetch('api.php?action=request_topup', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ amount, method })
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message);
                    await loadDataFromMySQL();
                } else {
                    showToast(data.message, 'error');
                }
            } catch (err) {
                showToast('Lỗi kết nối khi gửi yêu cầu nạp tiền.', 'error');
            }
        }

        async function handleApproveTopup(topupId, decision) {
            try {
                const res = await fetch('api.php?action=approve_topup', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ topupId, decision })
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message, decision === 'Approved' ? 'success' : 'warning');
                    await loadDataFromMySQL();
                } else {
                    showToast(data.message, 'error');
                }
            } catch (err) {
                showToast('Lỗi khi duyệt giao dịch nạp tiền.', 'error');
            }
        }

        function renderCustomerPortal() {
            if (!currentAuth || currentAuth.role !== 'customer') return;
            const activeCustomerId = String(currentAuth.id);
            const user = state.users.find(u => String(u.id) === activeCustomerId);
            if (!user) return;

            document.getElementById('cust-welcome-name').textContent = `${user.name} (KH #${user.id})`;
            document.getElementById('cust-tier-info').textContent = `Username: ${user.username || user.phone} • SĐT: ${user.phone || 'Chưa cập nhật'} • ${user.tier}`;
            document.getElementById('cust-wallet-balance').textContent = (user.balance || 0).toLocaleString('vi-VN') + ' đ';

            const phoneInput = document.getElementById('cust-new-phone');
            if (phoneInput && user.phone) phoneInput.value = user.phone;

            const myTopups = state.topups.filter(t => String(t.userId) === activeCustomerId);
            document.getElementById('cust-topups-list').innerHTML = myTopups.length
                ? `<table class="w-full text-left text-xs">
                    <thead><tr class="border-b text-slate-500">
                        <th class="py-2">Mã GD</th><th>Số tiền</th><th>Phương thức</th><th>Thời gian</th><th>Trạng thái</th>
                    </tr></thead>
                    <tbody class="divide-y">${myTopups.map(t => {
                        const badge = t.status === 'Approved'
                            ? '<span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 font-semibold">Đã cộng tiền</span>'
                            : t.status === 'Rejected'
                            ? '<span class="px-2 py-0.5 rounded-full bg-rose-100 text-rose-700 font-semibold">Từ chối</span>'
                            : '<span class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 font-semibold">Chờ Admin duyệt</span>';
                        return `<tr>
                            <td class="py-2 font-medium">#${t.id}</td>
                            <td class="font-bold text-slate-800">+${t.amount.toLocaleString('vi-VN')} đ</td>
                            <td>${t.method}</td>
                            <td class="text-slate-500">${t.createdAt}</td>
                            <td>${badge}</td>
                        </tr>`;
                    }).join('')}</tbody></table>`
                : '<p class="text-xs text-slate-400 py-4 text-center">Bạn chưa có giao dịch nạp tiền nào.</p>';

            const myVehicles = state.vehicles.filter(v => String(v.userId) === activeCustomerId);
            document.getElementById('cust-vehicles-list').innerHTML = myVehicles.length
                ? myVehicles.map(v => `
                    <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200 flex justify-between items-center">
                        <div>
                            <p class="font-bold text-slate-800">${v.plate}</p>
                            <p class="text-xs text-slate-500">${v.model} • Pin ${v.batteryKwh} kWh</p>
                        </div>
                        <span class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-emerald-100 text-emerald-700">${v.connector || 'CCS2'}</span>
                    </div>`).join('')
                : '<p class="text-sm text-slate-400">Tài khoản chưa đăng ký phương tiện.</p>';

            const custVehSelect = document.getElementById('cust-res-vehicle');
            custVehSelect.innerHTML = myVehicles.length
                ? myVehicles.map(v => `<option value="${v.plate}">${v.plate} (${v.model})</option>`).join('')
                : '<option value="">Chưa có xe đăng ký</option>';

            const custPortSelect = document.getElementById('cust-res-port');
            custPortSelect.innerHTML = state.ports.map(p => {
                const st = state.stations.find(s => String(s.id) === String(p.stationId));
                return `<option value="${p.id}">${st ? st.name : 'Trạm'} - ${p.code} (${p.maxKw}kW - ${p.status})</option>`;
            }).join('');

            const activeSession = state.sessions.find(s => String(s.userId) === activeCustomerId && s.status === 'Charging');
            document.getElementById('cust-active-session').innerHTML = activeSession
                ? `<div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 space-y-2.5">
                        <div class="flex justify-between font-bold text-emerald-900 text-sm">
                            <span>Xe ${activeSession.plate} • Cổng #${activeSession.portId}</span>
                            <span>${activeSession.soc}% Pin</span>
                        </div>
                        <div class="w-full bg-emerald-200 rounded-full h-2.5">
                            <div class="bg-emerald-600 h-2.5 rounded-full" style="width: ${activeSession.soc}%"></div>
                        </div>
                        <p class="text-xs text-emerald-800">Đã nạp: <b>${activeSession.energyKwh} kWh</b> • Công suất: <b>${activeSession.currentKw} kW</b> • Tạm tính: <b>${Math.round(activeSession.energyKwh * TARIFF_RATE).toLocaleString('vi-VN')} đ</b></p>
                   </div>`
                : '<div class="p-5 rounded-xl bg-slate-50 border border-dashed border-slate-200 text-xs text-slate-500 text-center">Hiện không có xe nào của bạn đang cắm sạc.</div>';

            const myRes = state.reservations.filter(r => String(r.userId) === activeCustomerId);
            document.getElementById('cust-reservations-list').innerHTML = myRes.length
                ? `<table class="w-full text-left text-sm">
                    <thead><tr class="border-b text-slate-500 text-xs">
                        <th class="py-2">Mã ĐC</th><th>Biển số</th><th>Cổng sạc</th><th>Thời gian</th><th>Trạng thái</th><th class="text-right">Thao tác</th>
                    </tr></thead>
                    <tbody class="divide-y">${myRes.map(r => `
                        <tr>
                            <td class="py-2.5 font-medium">#${r.id}</td>
                            <td class="font-semibold">${r.plate}</td>
                            <td>Cổng #${r.portId}</td>
                            <td>${r.startTime} - ${r.endTime}</td>
                            <td><span class="px-2 py-0.5 rounded-full text-xs font-medium ${r.status === 'Cancelled' ? 'bg-slate-100 text-slate-500' : 'bg-blue-50 text-blue-700'}">${r.status}</span></td>
                            <td class="text-right">${r.status !== 'Cancelled' ? `<button onclick="cancelReservation(${r.id})" class="text-xs text-rose-600 hover:underline">Hủy lịch</button>` : '-'}</td>
                        </tr>`).join('')}
                    </tbody></table>`
                : '<p class="text-sm text-slate-400">Bạn chưa có lịch đặt chỗ nào.</p>';

            const mySessions = state.sessions.filter(s => String(s.userId) === activeCustomerId);
            document.getElementById('cust-sessions-list').innerHTML = mySessions.length
                ? `<table class="w-full text-left text-sm">
                    <thead><tr class="border-b text-slate-500 text-xs">
                        <th class="py-2">Mã Phiên</th><th>Xe</th><th>Cổng</th><th>Điện năng</th><th>Thành tiền</th><th>Trạng thái</th>
                    </tr></thead>
                    <tbody class="divide-y">${mySessions.map(s => `
                        <tr>
                            <td class="py-2.5 font-medium">#${s.id}</td>
                            <td>${s.plate}</td>
                            <td>Cổng #${s.portId}</td>
                            <td class="font-semibold text-emerald-700">${s.energyKwh} kWh</td>
                            <td class="font-semibold">${Math.round(s.energyKwh * TARIFF_RATE).toLocaleString('vi-VN')} đ</td>
                            <td>${s.status}</td>
                        </tr>`).join('')}
                    </tbody></table>`
                : '<p class="text-sm text-slate-400">Chưa có lịch sử phiên sạc nào.</p>';
        }

        function switchTab(tabName) {
            document.querySelectorAll('main > section').forEach(sec => sec.classList.add('hidden'));
            document.querySelectorAll('.nav-btn').forEach(btn => {
                btn.classList.remove('tab-active');
                btn.classList.add('text-slate-600');
            });

            const targetView = document.getElementById(`view-${tabName}`);
            const targetNav = document.getElementById(`nav-${tabName}`);
            if (targetView) targetView.classList.remove('hidden');
            if (targetNav) {
                targetNav.classList.add('tab-active');
                targetNav.classList.remove('text-slate-600');
            }

            const titles = {
                dashboard: ['Tổng quan Vận hành', 'Giám sát thời gian thực hệ thống trạm sạc Smart City'],
                stations: ['Trạm & Cổng sạc', 'Quản lý trạng thái vật lý và thêm cổng sạc mới'],
                reservations: ['Quản lý Đặt chỗ', 'Theo dõi lịch hẹn và kiểm tra xung đột thời gian'],
                sessions: ['Phiên sạc & Hóa đơn', 'Lịch sử tiêu thụ điện năng và thanh toán'],
                users: ['Khách hàng & Xác nhận Nạp tiền', 'Tìm kiếm khách hàng, duyệt nạp tiền ví và cấp tài khoản mới']
            };
            if (titles[tabName]) {
                document.getElementById('page-title').textContent = titles[tabName][0];
                document.getElementById('page-subtitle').textContent = titles[tabName][1];
            }
        }

        function renderAll() {
            const pendingCount = state.topups.filter(t => t.status === 'Pending').length;
            const badge = document.getElementById('pending-topup-badge');
            if (badge) {
                if (pendingCount > 0) {
                    badge.textContent = pendingCount;
                    badge.classList.remove('hidden');
                } else {
                    badge.classList.add('hidden');
                }
            }

            if (currentAuth && currentAuth.role === 'admin') {
                renderDashboard();
                renderStations();
                renderReservations();
                renderSessions();
                renderAdminTopups();
                renderUsersAndVehicles();
            } else if (currentAuth && currentAuth.role === 'customer') {
                renderCustomerPortal();
            }
            lucide.createIcons();
        }

        function renderAdminTopups() {
            const tbody = document.getElementById('admin-topups-table-body');
            if (!tbody) return;
            const kw = (document.getElementById('admin-topup-search')?.value || '').trim().toLowerCase();
            const stFilter = document.getElementById('admin-topup-status-filter')?.value || 'all';

            const filtered = state.topups.filter(t => {
                const u = state.users.find(user => String(user.id) === String(t.userId));
                const uName = u ? u.name.toLowerCase() : '';
                const uPhone = u ? (u.phone || '').toLowerCase() : '';
                const matchKw = !kw || uName.includes(kw) || uPhone.includes(kw) || String(t.id) === kw;
                const matchSt = stFilter === 'all' || t.status === stFilter;
                return matchKw && matchSt;
            });

            tbody.innerHTML = filtered.length
                ? filtered.map(t => {
                    const u = state.users.find(user => String(user.id) === String(t.userId));
                    const statusHtml = t.status === 'Approved'
                        ? '<span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700">Đã xác nhận</span>'
                        : t.status === 'Rejected'
                        ? '<span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-100 text-rose-700">Đã từ chối</span>'
                        : '<span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">Chờ xác nhận</span>';

                    const actionHtml = t.status === 'Pending'
                        ? `<div class="inline-flex gap-1.5">
                               <button onclick="handleApproveTopup(${t.id}, 'Approved')" class="px-2.5 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold transition">Xác nhận nạp</button>
                               <button onclick="handleApproveTopup(${t.id}, 'Rejected')" class="px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-rose-100 text-slate-600 hover:text-rose-700 text-xs font-medium transition">Từ chối</button>
                           </div>`
                        : `<span class="text-xs text-slate-400">${t.approvedAt || 'Đã xử lý'}</span>`;

                    return `
                    <tr>
                        <td class="py-3 font-medium">#${t.id}</td>
                        <td class="font-semibold text-slate-800">${u ? u.name : 'KH #' + t.userId}</td>
                        <td class="text-slate-600">${u ? (u.phone || '-') : '-'}</td>
                        <td class="font-bold text-emerald-700">+${t.amount.toLocaleString('vi-VN')} đ</td>
                        <td>${t.method}</td>
                        <td class="text-xs text-slate-500">${t.createdAt}</td>
                        <td>${statusHtml}</td>
                        <td class="text-right">${actionHtml}</td>
                    </tr>`;
                }).join('')
                : '<tr><td colspan="8" class="py-6 text-center text-slate-400 text-xs">Không có yêu cầu nạp tiền nào phù hợp.</td></tr>';
        }

        function renderDashboard() {
            const availCount = state.ports.filter(p => p.status === 'Available').length;
            const chargingCount = state.ports.filter(p => p.status === 'Charging').length;
            const totalEnergy = state.sessions.reduce((sum, s) => sum + (Number(s.energyKwh) || 0), 0);
            const totalRevenue = Math.round(totalEnergy * TARIFF_RATE);

            document.getElementById('kpi-stations').textContent = `${state.stations.length} / ${state.ports.length}`;
            document.getElementById('kpi-available-ports').textContent = `${availCount} cổng đang sẵn sàng`;
            document.getElementById('kpi-charging').textContent = `${chargingCount} xe`;
            document.getElementById('kpi-energy').textContent = `${totalEnergy.toFixed(1)} kWh`;
            document.getElementById('kpi-revenue').textContent = `${totalRevenue.toLocaleString('vi-VN')} đ`;

            const statusGroups = [
                { label: 'Sẵn sàng (Available)', count: availCount, color: 'bg-emerald-500' },
                { label: 'Đang sạc (Charging)', count: chargingCount, color: 'bg-amber-500' },
                { label: 'Đã đặt trước (Reserved)', count: state.ports.filter(p => p.status === 'Reserved').length, color: 'bg-blue-500' },
                { label: 'Bảo trì (Maintenance)', count: state.ports.filter(p => p.status === 'Maintenance').length, color: 'bg-rose-500' }
            ];
            document.getElementById('dashboard-port-summary').innerHTML = statusGroups.map(g => `
                <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 border border-slate-100">
                    <div class="flex items-center gap-2.5">
                        <span class="w-2.5 h-2.5 rounded-full ${g.color}"></span>
                        <span class="text-xs font-medium text-slate-700">${g.label}</span>
                    </div>
                    <span class="text-sm font-bold text-slate-900">${g.count}</span>
                </div>
            `).join('');
        }

        function renderStations() {
            const newPortStation = document.getElementById('new-port-station');
            if (newPortStation) {
                newPortStation.innerHTML = state.stations.map(s => `<option value="${s.id}">${s.name}</option>`).join('');
            }

            const filterSelect = document.getElementById('station-filter');
            const selectedVal = filterSelect.value;
            filterSelect.innerHTML = `<option value="all">Tất cả các trạm (${state.stations.length})</option>` +
                state.stations.map(s => `<option value="${s.id}">${s.name}</option>`).join('');
            filterSelect.value = selectedVal || 'all';

            const filteredStations = selectedVal && selectedVal !== 'all'
                ? state.stations.filter(s => String(s.id) === selectedVal)
                : state.stations;

            document.getElementById('stations-grid').innerHTML = filteredStations.map(st => {
                const stPorts = state.ports.filter(p => String(p.stationId) === String(st.id));
                return `
                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm space-y-4">
                    <div>
                        <h4 class="font-bold text-slate-900 text-base">${st.name}</h4>
                        <p class="text-xs text-slate-500 mt-0.5">${st.address}</p>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        ${stPorts.map(p => {
                            const badge = p.status === 'Available' ? 'bg-emerald-100 text-emerald-700'
                                : p.status === 'Charging' ? 'bg-amber-100 text-amber-700'
                                : p.status === 'Reserved' ? 'bg-blue-100 text-blue-700'
                                : 'bg-rose-100 text-rose-700';
                            return `
                            <div class="p-3 rounded-xl border border-slate-200 bg-slate-50/60 flex flex-col justify-between gap-2">
                                <div class="flex justify-between items-center">
                                    <span class="font-bold text-xs text-slate-800">${p.code}</span>
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold ${badge}">${p.status}</span>
                                </div>
                                <div class="text-xs text-slate-500">${p.connector} • ${p.maxKw} kW</div>
                                ${p.plate ? `<div class="text-[11px] font-semibold text-amber-700">Xe: ${p.plate}</div>` : ''}
                            </div>`;
                        }).join('')}
                    </div>
                </div>`;
            }).join('');
        }

        function renderReservations() {
            const userSel = document.getElementById('res-user');
            userSel.innerHTML = state.users.map(u => `<option value="${u.id}">${u.name}</option>`).join('');
            syncReservationVehicles();

            const portSel = document.getElementById('res-port');
            portSel.innerHTML = state.ports.map(p => `<option value="${p.id}">${p.code} (${p.maxKw}kW)</option>`).join('');

            document.getElementById('reservations-table-body').innerHTML = state.reservations.length
                ? state.reservations.map(r => {
                    const u = state.users.find(user => String(user.id) === String(r.userId));
                    return `
                    <tr>
                        <td class="py-3 font-medium">#${r.id}</td>
                        <td>${u ? u.name : 'KH #' + r.userId}</td>
                        <td class="font-semibold">${r.plate}</td>
                        <td>Cổng #${r.portId}</td>
                        <td>${r.startTime} - ${r.endTime}</td>
                        <td><span class="px-2.5 py-0.5 rounded-full text-xs font-medium ${r.status === 'Cancelled' ? 'bg-slate-100 text-slate-500' : 'bg-blue-50 text-blue-700'}">${r.status}</span></td>
                        <td class="text-right">
                            ${r.status !== 'Cancelled' ? `<button onclick="cancelReservation(${r.id})" class="text-xs text-rose-600 hover:underline font-medium">Hủy</button>` : '-'}
                        </td>
                    </tr>`;
                }).join('')
                : '<tr><td colspan="7" class="py-6 text-center text-slate-400 text-xs">Chưa có dữ liệu đặt chỗ.</td></tr>';
        }

        function syncReservationVehicles() {
            const uid = document.getElementById('res-user').value;
            const userVehicles = state.vehicles.filter(v => String(v.userId) === String(uid));
            const vehSel = document.getElementById('res-vehicle');
            vehSel.innerHTML = userVehicles.length
                ? userVehicles.map(v => `<option value="${v.plate}">${v.plate} - ${v.model}</option>`).join('')
                : state.vehicles.map(v => `<option value="${v.plate}">${v.plate} - ${v.model}</option>`).join('');
        }

        function renderSessions() {
            document.getElementById('sessions-table-body').innerHTML = state.sessions.length
                ? state.sessions.map(s => `
                <tr>
                    <td class="py-3 font-medium">#${s.id}</td>
                    <td class="font-semibold">${s.plate}</td>
                    <td>Cổng #${s.portId}</td>
                    <td>${s.startTime} → ${s.endTime || 'Đang sạc'}</td>
                    <td class="font-semibold text-emerald-700">${s.energyKwh} kWh</td>
                    <td class="font-semibold">${Math.round(s.energyKwh * TARIFF_RATE).toLocaleString('vi-VN')} đ</td>
                    <td><span class="px-2.5 py-0.5 rounded-full text-xs font-medium ${s.status === 'Charging' ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700'}">${s.status}</span></td>
                </tr>`).join('')
                : '<tr><td colspan="7" class="py-6 text-center text-slate-400 text-xs">Chưa có dữ liệu phiên sạc.</td></tr>';
        }

        function renderUsersAndVehicles() {
            const uKw = (document.getElementById('admin-user-search')?.value || '').trim().toLowerCase();
            const filteredUsers = state.users.filter(u =>
                !uKw || u.name.toLowerCase().includes(uKw) || (u.phone || '').toLowerCase().includes(uKw) || (u.username || '').toLowerCase().includes(uKw) || String(u.id) === uKw
            );

            document.getElementById('users-table-body').innerHTML = filteredUsers.length
                ? filteredUsers.map(u => `
                <tr>
                    <td class="py-2.5 font-medium">#${u.id}</td>
                    <td class="font-semibold text-slate-800">${u.name}</td>
                    <td class="text-slate-600 font-mono text-xs">${u.username || u.phone || '-'}</td>
                    <td class="text-right font-bold text-emerald-700">${(u.balance || 0).toLocaleString('vi-VN')} đ</td>
                </tr>`).join('')
                : '<tr><td colspan="4" class="py-4 text-center text-slate-400 text-xs">Không tìm thấy khách hàng.</td></tr>';

            const vKw = (document.getElementById('admin-vehicle-search')?.value || '').trim().toLowerCase();
            const filteredVehicles = state.vehicles.filter(v => {
                const owner = state.users.find(u => String(u.id) === String(v.userId));
                const ownerName = owner ? owner.name.toLowerCase() : '';
                return !vKw || v.plate.toLowerCase().includes(vKw) || v.model.toLowerCase().includes(vKw) || ownerName.includes(vKw);
            });

            document.getElementById('vehicles-table-body').innerHTML = filteredVehicles.length
                ? filteredVehicles.map(v => {
                    const owner = state.users.find(u => String(u.id) === String(v.userId));
                    return `
                    <tr>
                        <td class="py-2.5 font-bold text-slate-800">${v.plate}</td>
                        <td>${v.model}</td>
                        <td>${v.batteryKwh} kWh</td>
                        <td>${owner ? owner.name : 'KH #' + v.userId}</td>
                    </tr>`;
                }).join('')
                : '<tr><td colspan="4" class="py-4 text-center text-slate-400 text-xs">Không tìm thấy phương tiện.</td></tr>';
        }

        function initChart() {
            const ctx = document.getElementById('powerChart');
            if (!ctx) return;
            powerChartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['06:00', '09:00', '12:00', '15:00', '18:00', '21:00'],
                    datasets: [{
                        label: 'Công suất tiêu thụ toàn hệ thống (kW)',
                        data: [120, 340, 290, 410, 560, 380],
                        borderColor: '#059669',
                        backgroundColor: 'rgba(16, 185, 129, 0.12)',
                        fill: true,
                        tension: 0.35
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false }
            });
        }

        function startLiveClock() {
            setInterval(() => {
                const now = new Date();
                const el = document.getElementById('live-clock');
                if (el) el.textContent = now.toLocaleTimeString('vi-VN');
            }, 1000);
        }

        async function loadDataFromMySQL() {
            try {
                const response = await fetch('api.php?action=get_all');
                const res = await response.json();
                if (res.requireLogin) {
                    document.getElementById('app-shell').classList.add('hidden');
                    document.getElementById('auth-screen').classList.remove('hidden');
                    return;
                }
                if (res.success) {
                    const db = res.data;
                    state.stations     = db.stations || [];
                    state.ports        = db.ports || [];
                    state.users        = db.users || [];
                    state.vehicles     = db.vehicles || [];
                    state.reservations = db.reservations || [];
                    state.sessions     = db.sessions || [];
                    state.topups       = db.topups || [];

                    state.sessions.forEach(s => {
                        if (s.status === 'Charging') {
                            const p = state.ports.find(port => String(port.id) === String(s.portId));
                            if (p) {
                                p.status = 'Charging';
                                p.plate = s.plate;
                            }
                        }
                    });
                    renderAll();
                } else {
                    showToast(res.message || 'Lỗi truy vấn dữ liệu MySQL', 'error');
                }
            } catch (err) {
                console.error(err);
                showToast('Không thể kết nối tới api.php', 'error');
            }
        }

        async function handleCreatePort(e) {
            e.preventDefault();
            const payload = {
                stationId: document.getElementById('new-port-station').value,
                code: document.getElementById('new-port-code').value,
                connector: document.getElementById('new-port-connector').value,
                maxKw: document.getElementById('new-port-kw').value
            };
            try {
                const res = await fetch('api.php?action=create_port', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message);
                    e.target.reset();
                    await loadDataFromMySQL();
                } else {
                    showToast(data.message, 'error');
                }
            } catch (err) {
                showToast('Lỗi khi thêm cổng sạc.', 'error');
            }
        }

        async function handleCreateUserVehicle(e) {
            e.preventDefault();
            const phone = document.getElementById('new-user-phone').value.trim();
            if (!isValidVNPhone(phone)) {
                showToast('Số điện thoại phải bắt đầu từ số 0 và có đúng 10 chữ số!', 'error');
                return;
            }
            const payload = {
                name: document.getElementById('new-user-name').value,
                phone: phone,
                username: phone,
                password: document.getElementById('new-user-pass').value,
                plate: document.getElementById('new-veh-plate').value,
                model: document.getElementById('new-veh-model').value || 'VinFast VF 8',
                batteryKwh: 82
            };
            try {
                const res = await fetch('api.php?action=create_user_vehicle', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message);
                    e.target.reset();
                    document.getElementById('new-user-pass').value = '123456';
                    await loadDataFromMySQL();
                } else {
                    showToast(data.message, 'error');
                }
            } catch (err) {
                showToast('Lỗi khi thêm khách hàng.', 'error');
            }
        }

        async function submitReservationToAPI(payload, errorBox) {
            try {
                const response = await fetch('api.php?action=create_reservation', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const result = await response.json();
                if (!result.success) {
                    errorBox.textContent = result.message;
                    errorBox.classList.remove('hidden');
                    showToast(result.message, 'error');
                } else {
                    errorBox.classList.add('hidden');
                    showToast(result.message || 'Đã lưu đặt chỗ thành công!');
                    await loadDataFromMySQL();
                }
            } catch (err) {
                showToast('Lỗi kết nối tới api.php khi tạo đặt chỗ.', 'error');
            }
        }

        async function handleCreateReservation(e) {
            e.preventDefault();
            const payload = {
                userId: document.getElementById('res-user').value,
                plate: document.getElementById('res-vehicle').value,
                portId: document.getElementById('res-port').value,
                startTime: document.getElementById('res-start').value,
                endTime: document.getElementById('res-end').value
            };
            await submitReservationToAPI(payload, document.getElementById('res-validation-msg'));
        }

        async function handleCustomerReservation(e) {
            e.preventDefault();
            const payload = {
                plate: document.getElementById('cust-res-vehicle').value,
                portId: document.getElementById('cust-res-port').value,
                startTime: document.getElementById('cust-res-start').value,
                endTime: document.getElementById('cust-res-end').value
            };
            await submitReservationToAPI(payload, document.getElementById('cust-res-error'));
        }

        async function cancelReservation(resId) {
            try {
                const response = await fetch('api.php?action=cancel_reservation', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ resId })
                });
                const result = await response.json();
                if (result.success) {
                    showToast(`Đã hủy đặt chỗ #${resId}`);
                    await loadDataFromMySQL();
                }
            } catch (err) {
                showToast('Không thể hủy đặt chỗ lúc này.', 'error');
            }
        }

        window.onload = async function () {
            lucide.createIcons();
            startLiveClock();
            try {
                const res = await fetch('api.php?action=check_auth');
                const data = await res.json();
                if (data.authenticated && data.auth) {
                    await enterAppWithAuth(data.auth);
                }
            } catch (err) {
                console.error(err);
            }
        };
    </script>
</body>
</html>