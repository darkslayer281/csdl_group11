<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once 'db.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$action = $_GET['action'] ?? 'get_all';

function pickCol(array $cols, array $candidates, $fallback = null) {
    foreach ($candidates as $cand) {
        foreach ($cols as $col) {
            if (strcasecmp($col, $cand) === 0) return $col;
        }
    }
    return $fallback;
}

function getTableCols(mysqli $conn, string $table): array {
    $cols = [];
    $res = $conn->query("SHOW COLUMNS FROM `$table`");
    while ($r = $res->fetch_assoc()) $cols[] = $r['Field'];
    return $cols;
}

// Kiểm tra số điện thoại: bắt đầu từ số 0 và có tổng 10 ký tự số
function validatePhone(string $phone): void {
    if (!preg_match('/^0\d{9}$/', $phone)) {
        throw new Exception("Số điện thoại không hợp lệ! Phải bắt đầu từ số 0 và có đúng 10 chữ số (VD: 0901234567).");
    }
}

// Tự động bổ sung cột đăng nhập (Username, Password), SoDuVi và bảng Admin/Nạp tiền
function ensureSystemSchema(mysqli $conn) {
    $userCols = getTableCols($conn, 'KHACH_HANG');
    if (!pickCol($userCols, ['SoDuVi'])) {
        $conn->query("ALTER TABLE KHACH_HANG ADD COLUMN SoDuVi DECIMAL(15,2) NOT NULL DEFAULT 1500000");
    }
    if (!pickCol($userCols, ['Username'])) {
        $conn->query("ALTER TABLE KHACH_HANG ADD COLUMN Username VARCHAR(50) NULL");
        $phoneCol = pickCol($userCols, ['SoDienThoai', 'SDT']);
        if ($phoneCol) {
            $conn->query("UPDATE KHACH_HANG SET Username = COALESCE(NULLIF(TRIM(`$phoneCol`), ''), CONCAT('kh', MaKH)) WHERE Username IS NULL");
        } else {
            $conn->query("UPDATE KHACH_HANG SET Username = CONCAT('kh', MaKH) WHERE Username IS NULL");
        }
    }
    if (!pickCol($userCols, ['Password'])) {
        $conn->query("ALTER TABLE KHACH_HANG ADD COLUMN Password VARCHAR(255) NOT NULL DEFAULT '123456'");
    }

    // Bảng tài khoản Quản trị viên
    $conn->query("
        CREATE TABLE IF NOT EXISTS QUAN_TRI_VIEN (
            MaAdmin INT AUTO_INCREMENT PRIMARY KEY,
            Username VARCHAR(50) NOT NULL UNIQUE,
            Password VARCHAR(255) NOT NULL,
            HoTen VARCHAR(100) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $chkAdmin = $conn->query("SELECT COUNT(*) AS c FROM QUAN_TRI_VIEN")->fetch_assoc();
    if ((int)$chkAdmin['c'] === 0) {
        $conn->query("INSERT INTO QUAN_TRI_VIEN (Username, Password, HoTen) VALUES ('admin', 'admin123', 'Quản trị viên Hệ thống')");
    }

    // Bảng yêu cầu nạp tiền
    $conn->query("
        CREATE TABLE IF NOT EXISTS YEU_CAU_NAP_TIEN (
            MaNap INT AUTO_INCREMENT PRIMARY KEY,
            MaKH INT NOT NULL,
            SoTien DECIMAL(15,2) NOT NULL,
            PhuongThuc VARCHAR(50) DEFAULT 'Chuyển khoản QR',
            TrangThai VARCHAR(30) DEFAULT 'Pending',
            ThoiGianTao DATETIME DEFAULT CURRENT_TIMESTAMP,
            ThoiGianDuyet DATETIME NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

try {
    ensureSystemSchema($conn);

    // 1. KIỂM TRA PHIÊN ĐĂNG NHẬP HIỆN TẠI
    if ($action === 'check_auth') {
        if (!empty($_SESSION['auth'])) {
            echo json_encode(['authenticated' => true, 'auth' => $_SESSION['auth']], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(['authenticated' => false], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    // 2. ĐĂNG NHẬP (ADMIN HOẶC KHÁCH HÀNG)
    if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input    = json_decode(file_get_contents('php://input'), true);
        $role     = $input['role'] ?? 'customer';
        $username = trim($input['username'] ?? '');
        $password = trim($input['password'] ?? '');

        if (!$username || !$password) {
            throw new Exception("Vui lòng nhập đầy đủ Tên đăng nhập và Mật khẩu!");
        }

        if ($role === 'admin') {
            $stmt = $conn->prepare("SELECT MaAdmin, Username, Password, HoTen FROM QUAN_TRI_VIEN WHERE Username = ? LIMIT 1");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $admin = $stmt->get_result()->fetch_assoc();

            if (!$admin || ($password !== $admin['Password'] && !password_verify($password, $admin['Password']))) {
                throw new Exception("Sai tài khoản hoặc mật khẩu Quản trị viên!");
            }

            $_SESSION['auth'] = [
                'role'     => 'admin',
                'id'       => (int)$admin['MaAdmin'],
                'username' => $admin['Username'],
                'name'     => $admin['HoTen']
            ];
            echo json_encode(['success' => true, 'auth' => $_SESSION['auth'], 'message' => 'Đăng nhập Quản trị viên thành công!'], JSON_UNESCAPED_UNICODE);
            exit;
        } else {
            $userCols  = getTableCols($conn, 'KHACH_HANG');
            $nameCol   = pickCol($userCols, ['HoTen', 'TenKH'], 'HoTen');
            $phoneCol  = pickCol($userCols, ['SoDienThoai', 'SDT'], 'SoDienThoai');

            $stmt = $conn->prepare("SELECT MaKH, `$nameCol` AS HoTen, Username, Password FROM KHACH_HANG WHERE Username = ? OR `$phoneCol` = ? LIMIT 1");
            $stmt->bind_param("ss", $username, $username);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();

            if (!$user || ($password !== $user['Password'] && !password_verify($password, $user['Password']))) {
                throw new Exception("Sai tên đăng nhập (SĐT) hoặc mật khẩu Khách hàng!");
            }

            $_SESSION['auth'] = [
                'role'     => 'customer',
                'id'       => (int)$user['MaKH'],
                'username' => $user['Username'],
                'name'     => $user['HoTen']
            ];
            echo json_encode(['success' => true, 'auth' => $_SESSION['auth'], 'message' => 'Xin chào ' . $user['HoTen'] . '!'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    // 3. KHÁCH HÀNG TỰ ĐĂNG KÝ TÀI KHOẢN MỚI
    if ($action === 'register_customer' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input    = json_decode(file_get_contents('php://input'), true);
        $name     = trim($input['name'] ?? '');
        $phone    = trim($input['phone'] ?? '');
        $username = trim($input['username'] ?? $phone);
        $password = trim($input['password'] ?? '');
        $plate    = strtoupper(trim($input['plate'] ?? ''));
        $model    = trim($input['model'] ?? 'VinFast VF 8');

        if (!$name || !$phone || !$password) {
            throw new Exception("Vui lòng nhập đầy đủ Họ tên, Số điện thoại và Mật khẩu!");
        }
        validatePhone($phone);

        $chk = $conn->prepare("SELECT MaKH FROM KHACH_HANG WHERE Username = ? LIMIT 1");
        $chk->bind_param("s", $username);
        $chk->execute();
        if ($chk->get_result()->fetch_assoc()) {
            throw new Exception("Tài khoản '$username' đã tồn tại, vui lòng dùng số khác!");
        }

        $newUserId = insertCustomerDynamic($conn, $name, $phone, '', $username, $password);

        if ($plate !== '') {
            $vehCols   = getTableCols($conn, 'PHUONG_TIEN');
            $vModelCol = pickCol($vehCols, ['DongXe', 'MauXe', 'LoaiXe']);
            $vBatCol   = pickCol($vehCols, ['DungLuongPin_kWh', 'DungLuongPin']);

            $vFields = ['MaKH', 'BienSo'];
            $vVals   = [$newUserId, "'" . $conn->real_escape_string($plate) . "'"];
            if ($vModelCol) { $vFields[] = "`$vModelCol`"; $vVals[] = "'" . $conn->real_escape_string($model) . "'"; }
            if ($vBatCol)   { $vFields[] = "`$vBatCol`";   $vVals[] = 80; }
            $conn->query("INSERT INTO PHUONG_TIEN (" . implode(', ', $vFields) . ") VALUES (" . implode(', ', $vVals) . ")");
        }

        $_SESSION['auth'] = [
            'role'     => 'customer',
            'id'       => $newUserId,
            'username' => $username,
            'name'     => $name
        ];
        echo json_encode(['success' => true, 'auth' => $_SESSION['auth'], 'message' => 'Đăng ký tài khoản thành công!'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 4. ĐĂNG XUẤT
    if ($action === 'logout') {
        session_destroy();
        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // CHẶN MỌI API NẾU CHƯA ĐĂNG NHẬP
    if (empty($_SESSION['auth'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'requireLogin' => true, 'message' => 'Vui lòng đăng nhập để tiếp tục!'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $auth = $_SESSION['auth'];

    // 5. KHÁCH HÀNG ĐỔI SỐ ĐIỆN THOẠI (BẮT ĐẦU TỪ 0, TỔNG 10 CHỮ SỐ)
    if ($action === 'update_phone' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input    = json_decode(file_get_contents('php://input'), true);
        $newPhone = trim($input['phone'] ?? '');
        $userId   = ($auth['role'] === 'customer') ? (int)$auth['id'] : (int)($input['userId'] ?? 0);

        validatePhone($newPhone);

        $userCols = getTableCols($conn, 'KHACH_HANG');
        $phoneCol = pickCol($userCols, ['SoDienThoai', 'SDT'], 'SoDienThoai');

        // Kiểm tra trùng số điện thoại với tài khoản khác
        $chk = $conn->prepare("SELECT MaKH FROM KHACH_HANG WHERE (`$phoneCol` = ? OR Username = ?) AND MaKH != ? LIMIT 1");
        $chk->bind_param("ssi", $newPhone, $newPhone, $userId);
        $chk->execute();
        if ($chk->get_result()->fetch_assoc()) {
            throw new Exception("Số điện thoại $newPhone đã được đăng ký cho tài khoản khác!");
        }

        // Cập nhật cả cột SoDienThoai và Username (nếu Username cũ đang là SĐT cũ)
        $stmt = $conn->prepare("UPDATE KHACH_HANG SET `$phoneCol` = ?, Username = ? WHERE MaKH = ?");
        $stmt->bind_param("ssi", $newPhone, $newPhone, $userId);
        $stmt->execute();

        if ($auth['role'] === 'customer') {
            $_SESSION['auth']['username'] = $newPhone;
        }

        echo json_encode([
            'success' => true,
            'auth'    => $_SESSION['auth'],
            'message' => "Đã cập nhật số điện thoại mới thành $newPhone (dùng số này cho lần đăng nhập sau)!"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 6. LẤY TOÀN BỘ DỮ LIỆU HỆ THỐNG
    if ($action === 'get_all') {
        $data = [
            'stations' => [],
            'ports' => [],
            'users' => [],
            'vehicles' => [],
            'reservations' => [],
            'sessions' => [],
            'topups' => []
        ];

        // TRAM_SAC
        $res = $conn->query("SELECT * FROM TRAM_SAC");
        while ($r = $res->fetch_assoc()) {
            $data['stations'][] = [
                'id' => $r['MaTram'] ?? reset($r),
                'name' => $r['TenTram'] ?? 'Trạm sạc',
                'address' => $r['DiaChi'] ?? ''
            ];
        }

        // CONG_SAC
        $portCols = getTableCols($conn, 'CONG_SAC');
        $cId      = pickCol($portCols, ['MaCong']);
        $cStation = pickCol($portCols, ['MaTram']);
        $cCode    = pickCol($portCols, ['SoHieuCong', 'MaSoCong', 'TenCong'], $cId);
        $cConn    = pickCol($portCols, ['ChuanKetNoi', 'LoaiCong', 'ChuanSac']);
        $cKw      = pickCol($portCols, ['CongSuat_kW', 'CongSuatToiDa', 'CongSuat']);
        $cStatus  = pickCol($portCols, ['TrangThaiCong', 'TrangThai']);

        $res = $conn->query("SELECT * FROM CONG_SAC");
        while ($r = $res->fetch_assoc()) {
            $rawStatus = $cStatus ? ($r[$cStatus] ?? 'Available') : 'Available';
            $status = 'Available';
            if (stripos($rawStatus, 'Sạc') !== false || stripos($rawStatus, 'Charging') !== false || stripos($rawStatus, 'Bận') !== false) $status = 'Charging';
            elseif (stripos($rawStatus, 'Đặt') !== false || stripos($rawStatus, 'Reserved') !== false) $status = 'Reserved';
            elseif (stripos($rawStatus, 'Bảo trì') !== false || stripos($rawStatus, 'Maintenance') !== false || stripos($rawStatus, 'Hỏng') !== false) $status = 'Maintenance';

            $data['ports'][] = [
                'id'        => $r[$cId],
                'stationId' => $cStation ? ($r[$cStation] ?? 1) : 1,
                'code'      => $r[$cCode] ?? ('PORT-' . $r[$cId]),
                'connector' => $cConn ? ($r[$cConn] ?? 'CCS2') : 'CCS2',
                'maxKw'     => $cKw ? (float)$r[$cKw] : 60.0,
                'status'    => $status,
                'plate'     => null
            ];
        }

        // KHACH_HANG
        $sqlUsers = ($auth['role'] === 'customer')
            ? "SELECT * FROM KHACH_HANG WHERE MaKH = " . (int)$auth['id']
            : "SELECT * FROM KHACH_HANG ORDER BY MaKH DESC";
        $res = $conn->query($sqlUsers);
        while ($r = $res->fetch_assoc()) {
            $data['users'][] = [
                'id'       => $r['MaKH'] ?? reset($r),
                'name'     => $r['HoTen'] ?? ($r['TenKH'] ?? 'Khách hàng'),
                'phone'    => $r['SoDienThoai'] ?? ($r['SDT'] ?? ''),
                'email'    => $r['Email'] ?? '',
                'username' => $r['Username'] ?? '',
                'tier'     => $r['HangThanhVien'] ?? 'Thành viên EV',
                'balance'  => isset($r['SoDuVi']) ? (float)$r['SoDuVi'] : 1500000
            ];
        }

        // PHUONG_TIEN
        $vehCols = getTableCols($conn, 'PHUONG_TIEN');
        $vId     = pickCol($vehCols, ['MaXe']);
        $vUser   = pickCol($vehCols, ['MaKH']);
        $vPlate  = pickCol($vehCols, ['BienSo']);
        $vBrand  = pickCol($vehCols, ['HangXe']);
        $vModel  = pickCol($vehCols, ['DongXe', 'MauXe', 'LoaiXe']);
        $vBat    = pickCol($vehCols, ['DungLuongPin_kWh', 'DungLuongPin']);

        $res = $conn->query("SELECT * FROM PHUONG_TIEN ORDER BY $vId DESC");
        while ($r = $res->fetch_assoc()) {
            $modelName = trim(($vBrand ? ($r[$vBrand] ?? '') : '') . ' ' . ($vModel ? ($r[$vModel] ?? 'Xe điện EV') : 'Xe điện EV'));
            $data['vehicles'][] = [
                'id'         => $r[$vId],
                'userId'     => $r[$vUser],
                'plate'      => $r[$vPlate],
                'model'      => $modelName ?: 'Xe điện EV',
                'batteryKwh' => $vBat ? (float)$r[$vBat] : 75.0,
                'connector'  => 'CCS2'
            ];
        }

        $vehicleMap = [];
        foreach ($data['vehicles'] as $v) $vehicleMap[$v['id']] = $v;

        // DAT_CHO
        $res = $conn->query("SELECT * FROM DAT_CHO ORDER BY MaDatCho DESC LIMIT 50");
        while ($r = $res->fetch_assoc()) {
            $veh = $vehicleMap[$r['MaXe'] ?? 0] ?? null;
            $startRaw = $r['TG_DatBatDau'] ?? ($r['ThoiGianBatDau'] ?? '');
            $endRaw   = $r['TG_DatKetThuc'] ?? ($r['ThoiGianKetThuc'] ?? '');
            $data['reservations'][] = [
                'id'        => $r['MaDatCho'] ?? reset($r),
                'userId'    => $r['MaKH'] ?? ($veh['userId'] ?? 1),
                'plate'     => $veh['plate'] ?? ('XE #' . ($r['MaXe'] ?? '')),
                'portId'    => $r['MaCong'] ?? 1,
                'startTime' => $startRaw ? date('H:i d/m', strtotime($startRaw)) : '',
                'endTime'   => $endRaw ? date('H:i d/m', strtotime($endRaw)) : '',
                'status'    => $r['TrangThaiDat'] ?? ($r['TrangThai'] ?? 'Confirmed')
            ];
        }

        // PHIEN_SAC
        $res = $conn->query("SELECT * FROM PHIEN_SAC ORDER BY MaPhien DESC LIMIT 50");
        while ($r = $res->fetch_assoc()) {
            $veh = $vehicleMap[$r['MaXe'] ?? 0] ?? null;
            $startRaw = $r['TG_BatDau'] ?? ($r['ThoiGianBatDau'] ?? '');
            $endRaw   = $r['TG_KetThuc'] ?? ($r['ThoiGianKetThuc'] ?? null);
            $rawSt    = $r['TrangThaiPhien'] ?? ($r['TrangThai'] ?? '');
            $isCharging = (empty($endRaw) || stripos($rawSt, 'Đang') !== false || stripos($rawSt, 'Charging') !== false);

            $data['sessions'][] = [
                'id'              => $r['MaPhien'] ?? reset($r),
                'userId'          => $veh['userId'] ?? 1,
                'plate'           => $veh['plate'] ?? ('XE #' . ($r['MaXe'] ?? '')),
                'portId'          => $r['MaCong'] ?? 1,
                'startTime'       => $startRaw ? date('H:i d/m', strtotime($startRaw)) : '',
                'endTime'         => $endRaw ? date('H:i d/m', strtotime($endRaw)) : null,
                'durationMinutes' => ($startRaw && $endRaw) ? max(1, round((strtotime($endRaw) - strtotime($startRaw)) / 60)) : 30,
                'energyKwh'       => (float)($r['DienNangTieuThu_kWh'] ?? ($r['SanLuongDien_kWh'] ?? ($r['DienNang_kWh'] ?? 0))),
                'currentKw'       => $isCharging ? 120.0 : 0,
                'soc'             => $isCharging ? 78 : 95,
                'status'          => $isCharging ? 'Charging' : 'Completed'
            ];
        }

        // YEU_CAU_NAP_TIEN
        $sqlTopups = ($auth['role'] === 'customer')
            ? "SELECT * FROM YEU_CAU_NAP_TIEN WHERE MaKH = " . (int)$auth['id'] . " ORDER BY MaNap DESC"
            : "SELECT * FROM YEU_CAU_NAP_TIEN ORDER BY MaNap DESC LIMIT 100";
        $res = $conn->query($sqlTopups);
        while ($r = $res->fetch_assoc()) {
            $data['topups'][] = [
                'id'         => (int)$r['MaNap'],
                'userId'     => (int)$r['MaKH'],
                'amount'     => (float)$r['SoTien'],
                'method'     => $r['PhuongThuc'],
                'status'     => $r['TrangThai'],
                'createdAt'  => $r['ThoiGianTao'] ? date('H:i d/m/Y', strtotime($r['ThoiGianTao'])) : '',
                'approvedAt' => $r['ThoiGianDuyet'] ? date('H:i d/m/Y', strtotime($r['ThoiGianDuyet'])) : null
            ];
        }

        echo json_encode(['success' => true, 'auth' => $auth, 'data' => $data], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 7. KHÁCH HÀNG GỬI YÊU CẦU NẠP TIỀN
    if ($action === 'request_topup' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input  = json_decode(file_get_contents('php://input'), true);
        $userId = ($auth['role'] === 'customer') ? (int)$auth['id'] : (int)($input['userId'] ?? 0);
        $amount = (float)($input['amount'] ?? 0);
        $method = trim($input['method'] ?? 'Chuyển khoản QR');

        if ($amount < 10000) throw new Exception("Số tiền nạp tối thiểu là 10.000 đ!");

        $stmt = $conn->prepare("INSERT INTO YEU_CAU_NAP_TIEN (MaKH, SoTien, PhuongThuc, TrangThai) VALUES (?, ?, ?, 'Pending')");
        $stmt->bind_param("ids", $userId, $amount, $method);
        $stmt->execute();

        echo json_encode(['success' => true, 'message' => 'Đã gửi yêu cầu nạp ' . number_format($amount, 0, ',', '.') . ' đ. Chờ Admin xác nhận!'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 8. ADMIN XÁC NHẬN / TỪ CHỐI NẠP TIỀN
    if ($action === 'approve_topup' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($auth['role'] !== 'admin') throw new Exception("Chỉ Quản trị viên mới có quyền xác nhận nạp tiền!");
        $input    = json_decode(file_get_contents('php://input'), true);
        $topupId  = (int)($input['topupId'] ?? 0);
        $decision = $input['decision'] ?? 'Approved';

        $row = $conn->query("SELECT * FROM YEU_CAU_NAP_TIEN WHERE MaNap = $topupId LIMIT 1")->fetch_assoc();
        if (!$row) throw new Exception("Không tìm thấy giao dịch #$topupId!");
        if ($row['TrangThai'] !== 'Pending') throw new Exception("Giao dịch này đã được xử lý trước đó!");

        if ($decision === 'Approved') {
            $maKH   = (int)$row['MaKH'];
            $soTien = (float)$row['SoTien'];
            $conn->begin_transaction();
            try {
                $conn->query("UPDATE KHACH_HANG SET SoDuVi = COALESCE(SoDuVi, 0) + $soTien WHERE MaKH = $maKH");
                $conn->query("UPDATE YEU_CAU_NAP_TIEN SET TrangThai = 'Approved', ThoiGianDuyet = NOW() WHERE MaNap = $topupId");
                $conn->commit();
            } catch (Exception $ex) {
                $conn->rollback();
                throw $ex;
            }
            echo json_encode(['success' => true, 'message' => 'Đã xác nhận nạp +' . number_format($soTien, 0, ',', '.') . ' đ vào ví khách hàng!'], JSON_UNESCAPED_UNICODE);
        } else {
            $conn->query("UPDATE YEU_CAU_NAP_TIEN SET TrangThai = 'Rejected', ThoiGianDuyet = NOW() WHERE MaNap = $topupId");
            echo json_encode(['success' => true, 'message' => "Đã từ chối yêu cầu nạp tiền #$topupId."], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    // 9. ADMIN TẠO KHÁCH HÀNG & PHƯƠNG TIỆN MỚI
    if ($action === 'create_user_vehicle' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($auth['role'] !== 'admin') throw new Exception("Chỉ Quản trị viên mới có quyền thêm khách hàng!");
        $input    = json_decode(file_get_contents('php://input'), true);
        $name     = trim($input['name'] ?? '');
        $phone    = trim($input['phone'] ?? '');
        $email    = trim($input['email'] ?? '');
        $username = trim($input['username'] ?? $phone);
        $password = trim($input['password'] ?? '123456');
        $plate    = strtoupper(trim($input['plate'] ?? ''));
        $model    = trim($input['model'] ?? 'VinFast VF 8');
        $bat      = (float)($input['batteryKwh'] ?? 80);

        if (!$name || !$phone) throw new Exception("Vui lòng nhập đầy đủ Họ tên và Số điện thoại!");
        validatePhone($phone);

        $newUserId = insertCustomerDynamic($conn, $name, $phone, $email, $username, $password);

        if ($plate !== '') {
            $vehCols   = getTableCols($conn, 'PHUONG_TIEN');
            $vModelCol = pickCol($vehCols, ['DongXe', 'MauXe', 'LoaiXe']);
            $vBatCol   = pickCol($vehCols, ['DungLuongPin_kWh', 'DungLuongPin']);

            $vFields = ['MaKH', 'BienSo'];
            $vVals   = [$newUserId, "'" . $conn->real_escape_string($plate) . "'"];
            if ($vModelCol) { $vFields[] = "`$vModelCol`"; $vVals[] = "'" . $conn->real_escape_string($model) . "'"; }
            if ($vBatCol)   { $vFields[] = "`$vBatCol`";   $vVals[] = $bat; }
            $conn->query("INSERT INTO PHUONG_TIEN (" . implode(', ', $vFields) . ") VALUES (" . implode(', ', $vVals) . ")");
        }

        echo json_encode(['success' => true, 'message' => "Đã tạo KH $name (User: $username | Pass: $password)!"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 10. ADMIN THÊM CỔNG SẠC MỚI
    if ($action === 'create_port' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($auth['role'] !== 'admin') throw new Exception("Chỉ Quản trị viên mới có quyền thêm cổng sạc!");
        $input     = json_decode(file_get_contents('php://input'), true);
        $stationId = (int)($input['stationId'] ?? 1);
        $code      = strtoupper(trim($input['code'] ?? ''));
        $connector = trim($input['connector'] ?? 'CCS2');
        $maxKw     = (float)($input['maxKw'] ?? 120);

        if (!$code) throw new Exception("Vui lòng nhập mã số hiệu cổng sạc!");

        $portCols  = getTableCols($conn, 'CONG_SAC');
        $cStation  = pickCol($portCols, ['MaTram']);
        $cCode     = pickCol($portCols, ['SoHieuCong', 'MaSoCong', 'TenCong']);
        $cConn     = pickCol($portCols, ['ChuanKetNoi', 'LoaiCong', 'ChuanSac']);
        $cKw       = pickCol($portCols, ['CongSuat_kW', 'CongSuatToiDa', 'CongSuat']);
        $cStatus   = pickCol($portCols, ['TrangThaiCong', 'TrangThai']);

        $fields = [];
        $values = [];
        if ($cStation) { $fields[] = "`$cStation`"; $values[] = $stationId; }
        if ($cCode)    { $fields[] = "`$cCode`";    $values[] = "'" . $conn->real_escape_string($code) . "'"; }
        if ($cConn)    { $fields[] = "`$cConn`";    $values[] = "'" . $conn->real_escape_string($connector) . "'"; }
        if ($cKw)      { $fields[] = "`$cKw`";      $values[] = $maxKw; }
        if ($cStatus)  { $fields[] = "`$cStatus`";  $values[] = "'Available'"; }

        $conn->query("INSERT INTO CONG_SAC (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $values) . ")");
        echo json_encode(['success' => true, 'message' => "Đã thêm cổng sạc $code thành công!"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 11. TẠO & HỦY ĐẶT CHỖ
    if ($action === 'create_reservation' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input  = json_decode(file_get_contents('php://input'), true);
        $userId = ($auth['role'] === 'customer') ? (int)$auth['id'] : (int)$input['userId'];
        $plate  = $conn->real_escape_string($input['plate']);
        $portId = (int)$input['portId'];
        $start  = date('Y-m-d ') . $input['startTime'] . ':00';
        $end    = date('Y-m-d ') . $input['endTime'] . ':00';

        $xeRow = $conn->query("SELECT MaXe FROM PHUONG_TIEN WHERE BienSo = '$plate' LIMIT 1")->fetch_assoc();
        if (!$xeRow) throw new Exception("Không tìm thấy biển số xe $plate trong hệ thống!");
        $maXe = (int)$xeRow['MaXe'];

        $stmt = $conn->prepare("INSERT INTO DAT_CHO (MaKH, MaXe, MaCong, TG_DatBatDau, TG_DatKetThuc, TrangThaiDat) VALUES (?, ?, ?, ?, ?, 'Confirmed')");
        $stmt->bind_param("iiiss", $userId, $maXe, $portId, $start, $end);
        $stmt->execute();

        echo json_encode(['success' => true, 'message' => 'Đã lưu đặt chỗ vào MySQL thành công!'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'cancel_reservation' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $resId = (int)$input['resId'];
        $conn->query("UPDATE DAT_CHO SET TrangThaiDat = 'Cancelled' WHERE MaDatCho = $resId");
        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

function insertCustomerDynamic(mysqli $conn, string $name, string $phone, string $email, string $username, string $password): int {
    $fields = [];
    $values = [];
    $resCols = $conn->query("SHOW COLUMNS FROM KHACH_HANG");
    while ($c = $resCols->fetch_assoc()) {
        $colName = $c['Field'];
        if (stripos($c['Extra'], 'auto_increment') !== false) continue;

        if (strcasecmp($colName, 'HoTen') === 0 || strcasecmp($colName, 'TenKH') === 0) {
            $fields[] = "`$colName`"; $values[] = "'" . $conn->real_escape_string($name) . "'";
        } elseif (strcasecmp($colName, 'SoDienThoai') === 0 || strcasecmp($colName, 'SDT') === 0) {
            $fields[] = "`$colName`"; $values[] = "'" . $conn->real_escape_string($phone) . "'";
        } elseif (strcasecmp($colName, 'Email') === 0) {
            $fields[] = "`$colName`"; $values[] = "'" . $conn->real_escape_string($email) . "'";
        } elseif (strcasecmp($colName, 'Username') === 0) {
            $fields[] = "`$colName`"; $values[] = "'" . $conn->real_escape_string($username) . "'";
        } elseif (strcasecmp($colName, 'Password') === 0) {
            $fields[] = "`$colName`"; $values[] = "'" . $conn->real_escape_string($password) . "'";
        } elseif (strcasecmp($colName, 'SoDuVi') === 0) {
            $fields[] = "`$colName`"; $values[] = "500000";
        } elseif ($c['Null'] === 'NO' && $c['Default'] === null) {
            $fields[] = "`$colName`";
            if (preg_match('/int|decimal|float|double/i', $c['Type'])) $values[] = "0";
            elseif (preg_match('/date|time/i', $c['Type'])) $values[] = "NOW()";
            else $values[] = "'Cá nhân'";
        }
    }
    $conn->query("INSERT INTO KHACH_HANG (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $values) . ")");
    return (int)$conn->insert_id;
}
?>