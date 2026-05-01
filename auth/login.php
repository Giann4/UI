<?php session_start(); ?>

<?php
$registered = isset($_GET['registered']) ? $_GET['registered'] : '';
$error = isset($_GET['error']) ? $_GET['error'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, Helvetica, sans-serif;
        }

        body.login-page {
            min-height: 100vh;
            background: url('../assets/southern-night.png') no-repeat center center/cover;
            position: relative;
            overflow-x: hidden;
        }

        .login-bg-overlay {
            position: fixed;
            inset: 0;
            background: linear-gradient(135deg, rgba(0, 40, 20, 0.42), rgba(0, 0, 0, 0.32));
            backdrop-filter: blur(3px);
            z-index: 1;
        }

        .login-wrapper {
            position: relative;
            z-index: 2;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px 20px;
        }

        .login-card {
            position: relative;
            width: 100%;
            max-width: 1080px;
            display: grid;
            grid-template-columns: 0.92fr 1.08fr;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.22);
            border-radius: 30px;
            overflow: hidden;
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.28);
            transition: none;
        }

        .login-card:hover {
            transform: none;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.28);
            border-color: rgba(255, 255, 255, 0.22);
        }

        .divider-glow {
            position: absolute;
            top: 10%;
            left: 42.5%;
            transform: translateX(-50%);
            width: 2px;
            height: 80%;
            z-index: 5;
            background: linear-gradient(
                to bottom,
                rgba(255,255,255,0),
                rgba(255,255,255,0.95),
                rgba(255,255,255,0)
            );
            box-shadow:
                0 0 10px rgba(255,255,255,0.55),
                0 0 20px rgba(0,255,140,0.30),
                0 0 38px rgba(0,255,140,0.18);
            animation: glowMove 3s ease-in-out infinite;
            pointer-events: none;
        }

        @keyframes glowMove {
            0% {
                opacity: 0.55;
                transform: translateX(-50%) scaleY(0.96);
            }
            50% {
                opacity: 1;
                transform: translateX(-50%) scaleY(1);
            }
            100% {
                opacity: 0.55;
                transform: translateX(-50%) scaleY(0.96);
            }
        }

        .login-form-side {
            background: rgba(255, 255, 255, 0.93);
            padding: 55px 42px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            z-index: 2;
        }

        .form-title {
            text-align: center;
            font-size: 36px;
            font-weight: 800;
            color: #0a5c2d;
            margin-bottom: 10px;
            letter-spacing: 1px;
        }

        .form-subtitle {
            text-align: center;
            font-size: 14px;
            color: #666;
            margin-bottom: 22px;
            font-weight: 600;
        }

        .success-message,
        .error-message {
            margin-bottom: 16px;
            padding: 12px 14px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 700;
            text-align: center;
        }

        .success-message {
            background: #e9fff1;
            color: #0a7a3d;
            border: 1px solid #9de0b7;
        }

        .error-message {
            background: #ffe9e9;
            color: #b10000;
            border: 1px solid #ffb3b3;
        }

        .form-area {
            width: 100%;
        }

        .input-group {
            position: relative;
            margin-bottom: 22px;
        }

        .input-group input {
            width: 100%;
            height: 58px;
            padding: 20px 16px 10px 16px;
            border: 2px solid #cfd8d3;
            border-radius: 16px;
            outline: none;
            font-size: 16px;
            background: #fff;
            color: #222;
            transition: 0.3s ease;
        }

        .input-group input:focus {
            border-color: #0bb15d;
            box-shadow: 0 0 0 4px rgba(11, 177, 93, 0.12);
        }

        .input-group label {
            position: absolute;
            left: 16px;
            top: 18px;
            font-size: 15px;
            color: #666;
            pointer-events: none;
            transition: 0.25s ease;
            background: #fff;
            padding: 0 6px;
            border-radius: 10px;
        }

        .input-group input:focus + label,
        .input-group input:not(:placeholder-shown) + label {
            top: -9px;
            left: 12px;
            font-size: 12px;
            color: #0a8a45;
            font-weight: 700;
        }

        .password-group input {
            padding-right: 54px;
        }

        .toggle-password {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            font-size: 18px;
            color: #555;
            user-select: none;
            z-index: 3;
            line-height: 1;
        }

        .green-btn {
            width: 100%;
            height: 55px;
            border: none;
            border-radius: 16px;
            background: linear-gradient(135deg, #10c766, #06984b);
            color: #fff;
            font-size: 17px;
            font-weight: 800;
            letter-spacing: 0.5px;
            cursor: pointer;
            margin-top: 6px;
            transition: none;
            box-shadow: 0 12px 24px rgba(5, 143, 72, 0.25);
        }

        .green-btn:hover {
            transform: none;
            box-shadow: 0 12px 24px rgba(5, 143, 72, 0.25);
        }

        .forgot-link {
            display: block;
            text-align: center;
            margin-top: 16px;
            text-decoration: none;
            color: #0a5c2d;
            font-size: 14px;
            font-weight: 700;
        }

        .forgot-link:hover {
            color: #13a257;
            text-decoration: underline;
        }

        .text-link {
            margin-top: 20px;
            text-align: center;
            font-size: 15px;
            color: #333;
            font-weight: 600;
        }

        .text-link a {
            color: #6a41ff;
            text-decoration: none;
            font-weight: 800;
        }

        .text-link a:hover {
            text-decoration: underline;
        }

        .login-header {
            position: relative;
            padding: 65px 48px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            color: #fff;
            background: linear-gradient(135deg,
                #0f5132 0%,
                #0b6b40 28%,
                #0d8c4e 62%,
                #10b15d 100%
            );
            box-shadow: inset 0 0 60px rgba(0,0,0,0.22);
            z-index: 2;
            overflow: hidden;
            isolation: isolate;
        }

        .login-header::before {
            content: "";
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at top right, rgba(255,255,255,0.12), transparent 34%),
                radial-gradient(circle at bottom left, rgba(255,255,255,0.05), transparent 28%);
            pointer-events: none;
            z-index: 1;
        }

        .login-header::after {
            content: "";
            position: absolute;
            inset: 0;
            background: url('../assets/logo2.png') no-repeat center center;
            background-size: 360px;
            opacity: 0.42;
            filter: blur(0.6px) drop-shadow(0 0 16px rgba(255,255,255,0.18));
            pointer-events: none;
            z-index: 1;
            transform: scale(1.03);
        }

        .school-title,
        .school-subtitle {
            position: relative;
            z-index: 2;
        }

        .school-title {
            font-size: 38px;
            line-height: 1.12;
            font-weight: 900;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 22px;
            color: #ffffff;
            text-shadow:
                0 4px 12px rgba(0, 0, 0, 0.28),
                0 0 14px rgba(255,255,255,0.10);
        }

        .school-subtitle {
            font-size: 31px;
            line-height: 1.35;
            color: rgba(255, 255, 255, 0.97);
            font-weight: 800;
            margin-top: 4px;
            text-shadow: 0 3px 10px rgba(0, 0, 0, 0.25);
            max-width: 430px;
        }

        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.55);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 999;
            padding: 20px;
        }

        .modal-overlay.show {
            display: flex;
        }

        .forgot-modal-box,
        .contact-modal-box {
            position: relative;
            width: 100%;
            max-width: 500px;
            border-radius: 22px;
            padding: 30px 28px;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.25);
            animation: popupFade 0.25s ease;
        }

        .forgot-modal-box {
            background: #fff;
        }

        .contact-modal-box {
            max-width: 1100px;
            background: linear-gradient(135deg,
                #0f5132 0%,
                #0b6b40 28%,
                #0d8c4e 62%,
                #10b15d 100%
            );
            box-shadow:
                0 25px 60px rgba(0, 0, 0, 0.30),
                inset 0 0 50px rgba(0,0,0,0.18);
            border: 1px solid rgba(255,255,255,0.18);
            overflow: hidden;
            isolation: isolate;
        }

        .contact-modal-box::before {
            content: "";
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at top right, rgba(255,255,255,0.10), transparent 30%),
                radial-gradient(circle at bottom left, rgba(255,255,255,0.05), transparent 25%);
            pointer-events: none;
            z-index: 1;
        }

        .contact-modal-box::after {
            content: "";
            position: absolute;
            inset: 0;
            background: url('../assets/logo2.png') no-repeat center center;
            background-size: 360px;
            opacity: 0.12;
            filter: blur(0.8px);
            pointer-events: none;
            z-index: 1;
        }

        .forgot-modal-box h2,
        .contact-modal-box h2 {
            text-align: center;
            margin-bottom: 18px;
            font-size: 28px;
            font-weight: 800;
            position: relative;
            z-index: 2;
        }

        .forgot-modal-box h2 {
            color: #0a5c2d;
        }

        .contact-modal-box h2 {
            color: #ffffff;
            letter-spacing: 1px;
            text-shadow: 0 3px 10px rgba(0,0,0,0.22);
        }

        .close-modal,
        .contact-close {
            position: absolute;
            top: 12px;
            right: 18px;
            font-size: 30px;
            cursor: pointer;
            font-weight: bold;
            z-index: 3;
        }

        .close-modal {
            color: #444;
        }

        .contact-close {
            color: #ffffff;
        }

        .question-icon {
            position: absolute;
            top: 14px;
            left: 18px;
            width: 34px;
            height: 34px;
            background: #0bb15d;
            color: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            cursor: pointer;
            font-size: 18px;
        }

        .forgot-content p {
            font-size: 16px;
            color: #333;
            line-height: 1.8;
            margin-bottom: 10px;
            text-align: center;
        }

        .red-mark {
            color: red;
            font-weight: bold;
            margin-right: 5px;
        }

        .contact-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-top: 20px;
            position: relative;
            z-index: 2;
        }

        .contact-card {
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.16);
            border-radius: 20px;
            padding: 22px 18px;
            text-align: center;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            box-shadow: inset 0 0 14px rgba(255,255,255,0.04);
            transition: none;
        }

        .contact-card:hover {
            transform: none;
            box-shadow: inset 0 0 14px rgba(255,255,255,0.04);
        }

        .profile-icon {
            width: 95px;
            height: 95px;
            margin: 0 auto 14px;
            border-radius: 50%;
            overflow: hidden;
            border: 4px solid #16d26e;
            background: rgba(255,255,255,0.10);
            box-shadow: 0 0 16px rgba(22,210,110,0.18);
        }

        .profile-icon img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .contact-card h3 {
            color: #ffffff;
            font-size: 18px;
            margin-bottom: 12px;
            text-shadow: 0 2px 8px rgba(0,0,0,0.18);
        }

        .contact-card p {
            font-size: 14px;
            color: rgba(255,255,255,0.94);
            line-height: 1.6;
            margin-bottom: 8px;
        }

        .contact-card strong {
            color: #ffffff;
        }

        @keyframes popupFade {
            from {
                opacity: 0;
                transform: scale(0.96);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        @media (max-width: 900px) {
            .login-card {
                grid-template-columns: 1fr;
            }

            .divider-glow {
                display: none;
            }

            .login-header {
                padding: 40px 28px;
                text-align: center;
            }

            .login-header::after {
                background-size: 240px;
                opacity: 0.16;
            }

            .school-title {
                font-size: 28px;
            }

            .school-subtitle {
                font-size: 24px;
                max-width: 100%;
                margin: 0 auto;
            }

            .login-form-side {
                padding: 35px 25px;
            }
        }

        @media (max-width: 500px) {
            .login-header::after {
                background-size: 180px;
                opacity: 0.14;
            }

            .school-title {
                font-size: 22px;
            }

            .school-subtitle {
                font-size: 18px;
            }

            .form-title {
                font-size: 28px;
            }

            .input-group input {
                height: 54px;
                font-size: 15px;
            }

            .green-btn {
                height: 52px;
                font-size: 15px;
            }

            .contact-modal-box {
                max-width: 100%;
                padding: 24px 16px;
            }
        }
    </style>
</head>
<body class="login-page">

    <div class="login-bg-overlay"></div>

    <div class="login-wrapper">
        <div class="login-card">

            <div class="divider-glow"></div>

            <div class="login-form-side">
                <h1 class="form-title">LOG IN</h1>
                <p class="form-subtitle">Enter your account to continue</p>

                <?php if ($registered === '1'): ?>
                    <div class="success-message">
                        Account created successfully. You can now log in.
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="error-message">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form action="process_login.php" method="POST" class="form-area">
                    <div class="input-group">
                        <input type="email" name="email" id="email" placeholder=" " required>
                        <label for="email">Email</label>
                    </div>

                    <div class="input-group password-group">
                        <input type="password" name="password" id="loginPassword" placeholder=" " required>
                        <label for="loginPassword">Password</label>
                        <span class="toggle-password" onclick="togglePassword('loginPassword', this)">👁</span>
                    </div>

                    <button type="submit" class="green-btn">SIGN IN</button>
                    <a href="#" id="forgotPasswordBtn" class="forgot-link">Forgot Password?</a>
                </form>

                <p class="text-link">
                    Don’t have an account?
                    <a href="register.php">Create</a>
                </p>
            </div>

            <div class="login-header">
                <h2 class="school-title">SOUTHERN PHILIPPINES INSTITUTE OF SCIENCE AND TECHNOLOGY</h2>
                <p class="school-subtitle">Online Clearance Management System</p>
            </div>

        </div>
    </div>

    <div id="forgotModal" class="modal-overlay">
        <div class="forgot-modal-box">
            <span class="close-modal" id="closeForgotModal">&times;</span>
            <span class="question-icon" id="openContactModal">?</span>

            <h2>Forgot Password?</h2>

            <div class="forgot-content">
                <p><span class="red-mark">!</span>Password reset is managed by the administrator.</p>
                <p>Kindly contact the admin for assistance.</p>
                <p>Please click the question mark reminder for your OLD PASSWORD.</p>
            </div>
        </div>
    </div>

    <div id="contactModal" class="modal-overlay">
        <div class="contact-modal-box">
            <span class="contact-close" id="closeContactModal">&times;</span>

            <h2>CONTACT US</h2>

            <div class="contact-cards">
                <div class="contact-card">
                    <div class="profile-icon">
                        <img src="../assets/gian.jpg" alt="Gian Esio">
                    </div>
                    <h3>GIAN ESIO</h3>
                    <p><strong>NO.</strong><br>09851642711</p>
                    <p><strong>FB:</strong><br>GIAN ESIO</p>
                    <p><strong>GMAIL:</strong><br>c23-4908-01</p>
                </div>

                <div class="contact-card">
                    <div class="profile-icon">
                        <img src="../assets/mark.jpg" alt="Mark Paredes">
                    </div>
                    <h3>MARK PAREDES</h3>
                    <p><strong>NO.</strong><br>09925383649</p>
                    <p><strong>FB:</strong><br>MARK PAREDES</p>
                    <p><strong>GMAIL:</strong><br>c23-4908-02</p>
                </div>

                <div class="contact-card">
                    <div class="profile-icon">
                        <img src="../assets/cj.jpg" alt="CJ Balog">
                    </div>
                    <h3>CJ BALOG</h3>
                    <p><strong>NO.</strong><br>09602097975</p>
                    <p><strong>FB:</strong><br>CJ BALOG</p>
                    <p><strong>GMAIL:</strong><br>c23-4908-03</p>
                </div>

                <div class="contact-card">
                    <div class="profile-icon">
                        <img src="../assets/gerald.jpg" alt="Gerald Mamay">
                    </div>
                    <h3>GERALD MAMAY</h3>
                    <p><strong>NO.</strong><br>09477893124</p>
                    <p><strong>FB:</strong><br>HERALDO</p>
                    <p><strong>GMAIL:</strong><br>c23-4908-04</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        function togglePassword(inputId, icon) {
            const input = document.getElementById(inputId);

            if (input.type === "password") {
                input.type = "text";
                icon.textContent = "🙈";
            } else {
                input.type = "password";
                icon.textContent = "👁";
            }
        }

        const forgotBtn = document.getElementById("forgotPasswordBtn");
        const forgotModal = document.getElementById("forgotModal");
        const closeForgotModal = document.getElementById("closeForgotModal");

        const openContactModal = document.getElementById("openContactModal");
        const contactModal = document.getElementById("contactModal");
        const closeContactModal = document.getElementById("closeContactModal");

        forgotBtn.addEventListener("click", function(e) {
            e.preventDefault();
            forgotModal.classList.add("show");
        });

        closeForgotModal.addEventListener("click", function() {
            forgotModal.classList.remove("show");
        });

        forgotModal.addEventListener("click", function(e) {
            if (e.target === forgotModal) {
                forgotModal.classList.remove("show");
            }
        });

        openContactModal.addEventListener("click", function() {
            forgotModal.classList.remove("show");
            contactModal.classList.add("show");
        });

        closeContactModal.addEventListener("click", function() {
            contactModal.classList.remove("show");
            forgotModal.classList.add("show");
        });

        contactModal.addEventListener("click", function(e) {
            if (e.target === contactModal) {
                contactModal.classList.remove("show");
                forgotModal.classList.add("show");
            }
        });
    </script>

</body>
</html>