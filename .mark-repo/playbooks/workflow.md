# Delivery workflow

1. Baseline: instructions, status, git diff, tests, runtime và schema.
2. Specify: acceptance + non-goal + risk.
3. Plan: exact files, test-first, implementation, verification, recovery.
4. Implement một lát cắt end-to-end nhỏ.
5. Review diff và dữ liệu thay đổi.
6. Verify focused → integration → full → security/build.
7. UAT bằng hành trình người dùng thật.
8. Checkpoint; chỉ sang task tiếp theo sau khi đạt.

Không trộn nhiều phase lớn trong một thay đổi. Khi gặp lỗi, quay lại bước đầu tiên
có giả định sai thay vì vá thêm UI hoặc hardcode.
