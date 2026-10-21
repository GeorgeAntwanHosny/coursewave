<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Certificate {{ $certificate->certificate_number }}</title>
    <style>
        @page { margin: 0; }
        body { margin: 0; font-family: 'DejaVu Sans', sans-serif; color: #1f2937; }
        .frame { position: absolute; top: 24px; left: 24px; right: 24px; bottom: 24px; border: 6px solid #4338ca; }
        .inner { position: absolute; top: 12px; left: 12px; right: 12px; bottom: 12px; border: 1px solid #a5b4fc; text-align: center; }
        .brand { margin-top: 48px; font-size: 14px; letter-spacing: 6px; text-transform: uppercase; color: #4338ca; }
        .title { margin-top: 18px; font-size: 38px; font-weight: bold; color: #111827; }
        .lead { margin-top: 30px; font-size: 14px; color: #6b7280; }
        .name { margin: 14px 60px 0 60px; padding-bottom: 8px; border-bottom: 1px solid #d1d5db; font-size: 32px; font-weight: bold; color: #4338ca; }
        .course { margin: 14px 60px 0 60px; font-size: 24px; font-weight: bold; }
        .by { margin-top: 18px; font-size: 14px; color: #6b7280; }
        .footer { position: absolute; left: 40px; right: 40px; bottom: 28px; font-size: 11px; color: #6b7280; }
        .footer td { vertical-align: bottom; }
        .mono { font-family: 'DejaVu Sans Mono', monospace; color: #111827; }
    </style>
</head>
<body>
<div class="frame">
    <div class="inner">
        <div class="brand">Learn Ledger</div>
        <div class="title">Certificate of Completion</div>
        <div class="lead">This certifies that</div>
        <div class="name">{{ $certificate->student_name }}</div>
        <div class="lead">has successfully completed the course</div>
        <div class="course">{{ $certificate->course_title }}</div>
        <div class="by">Taught by {{ $certificate->instructor_name }}</div>

        <table class="footer" width="100%">
            <tr>
                <td align="left">Issued<br><span class="mono">{{ $certificate->issued_at->format('F j, Y') }}</span></td>
                <td align="center">Certificate no.<br><span class="mono">{{ $certificate->certificate_number }}</span></td>
                <td align="right">Verify at<br><span class="mono">{{ $verificationUrl }}</span></td>
            </tr>
        </table>
    </div>
</div>
</body>
</html>
