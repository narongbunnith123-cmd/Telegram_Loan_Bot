<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Loan Bot</title>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family:'DM Sans',sans-serif; background:#0d1117; color:#e6edf3; min-height:100vh; display:flex; align-items:center; justify-content:center; }
        h1,h2 { font-family:'Syne',sans-serif; }
        .form-input { width:100%; background:#0d1117; border:1px solid #30363d; border-radius:8px; padding:10px 14px; color:#e6edf3; font-size:14px; outline:none; transition:border-color .15s; }
        .form-input:focus { border-color:#22c55e; }
        .btn-primary { width:100%; background:#22c55e; color:#0d1117; padding:10px; border-radius:8px; font-weight:600; font-size:14px; cursor:pointer; border:none; transition:background .15s; font-family:'DM Sans',sans-serif; }
        .btn-primary:hover { background:#16a34a; }
    </style>
</head>
<body>
    <div style="width:380px;">
        <div class="text-center mb-8">
            <div class="w-14 h-14 rounded-2xl flex items-center justify-center mx-auto mb-4" style="background:#22c55e18;border:1px solid #22c55e44;">
                <svg viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2" class="w-7 h-7">
                    <path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/>
                </svg>
            </div>
            <h1 style="font-size:24px;font-weight:800;color:#fff;">LoanBot</h1>
            <p style="color:#8b949e;font-size:14px;margin-top:4px;">Sign in to your dashboard</p>
        </div>

        <div style="background:#161b22;border:1px solid #21262d;border-radius:16px;padding:28px;">
            @if(session('status'))
                <div style="background:#1c2a1c;border:1px solid #2ea043;border-radius:8px;padding:10px 14px;font-size:13px;color:#3fb950;margin-bottom:16px;">
                    {{ session('status') }}
                </div>
            @endif
            @if($errors->any())
                <div style="background:#2d1a1a;border:1px solid #da3633;border-radius:8px;padding:10px 14px;font-size:13px;color:#f85149;margin-bottom:16px;">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}">
                @csrf
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-size:13px;font-weight:500;color:#8b949e;margin-bottom:6px;">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}"
                           class="form-input" placeholder="admin@example.com" required autofocus>
                </div>
                <div style="margin-bottom:20px;">
                    <label style="display:block;font-size:13px;font-weight:500;color:#8b949e;margin-bottom:6px;">Password</label>
                    <input type="password" name="password" class="form-input" placeholder="••••••••" required>
                </div>
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
                    <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:#8b949e;cursor:pointer;">
                        <input type="checkbox" name="remember" style="accent-color:#22c55e;"> Remember me
                    </label>
                </div>
                <button type="submit" class="btn-primary">Sign In</button>
            </form>
        </div>
    </div>
</body>
</html>
