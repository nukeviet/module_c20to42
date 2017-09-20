# Hướng dẫn sử dụng tool chuyển dữ liệu từ bản NukeViet 2.0 lên bản 4.2
1. Nâng cấp dữ liệu module Users (Tài khoản)
- Mở file users2.0.php tìm đến dòng $prefix2 = "prefix_nkv2"; $user_prefix2 = "prefix_nkv2"; và thay ‘prefix_nkv2’ bằng tiếp đầu tố bảng dữ liệu NukeViet 2 của bạn.
- Xóa hết tất cả tài khoản thành viên (nếu có).
- Truy cập đường dẫn http:/domain/users2.0.php.
- Sử dụng tool lấy lại mật khẩu nếu muốn đăng nhập vào quản trị với link sau: https://github.com/nukeviet/set-password 
2. Nâng cấp dữ liệu module News (Tin tức)
- - Mở file news2.0.php tìm đến dòng $prefix2 = "prefix_nkv2"; $user_prefix2 = "prefix_nkv2"; và thay ‘prefix_nkv2’ bằng tiếp đầu tố bảng dữ liệu NukeViet 2 của bạn.
- Xóa hết tất cả dữ liệu module news hoặc module ảo của news (nếu có) (Có thể thực hiện thao tác “Cài lại”)
- Copy thư mục ảnh upload của news ở NukeViet 2 vào thư mục tương ứng của NukeViet 4
- Truy cập đường dẫn http:/domain/news2.0.php.
