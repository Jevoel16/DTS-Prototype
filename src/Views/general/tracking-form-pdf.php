<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Document Tracking Form</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        html, body {
            width: 210mm;
            height: 297mm;
            font-family: 'Helvetica', 'Arial', sans-serif;
        }
        .form-container {
            width: 100%;
            height: 148.5mm;
            padding: 7mm;
            page-break-inside: avoid;
        }
        .cut-line {
            padding: 25px;
            border-bottom: 2px dashed #000;
        }
        .header {
            padding-top: 40px;
            text-align: center;
            margin-bottom: 8px;
            width: 100%;
        }
        .header img {
            width: 120px;
            height: auto;
            display: block;
            margin: 0 auto 5px auto;
        }
        .header p {
            margin: 0;
            font-size: 12px;
            text-align: center;
        }
        .header .division {
            font-weight: bold;
            margin-top: 3px;
            font-size: 18px;
            text-align: center;
        }
        .title {
            text-align: center;
            font-weight: bold;
            text-decoration: underline;
            font-size: 18px;
            margin-bottom: 20px;
        }
        .content-table {
            width: 100%;
            border-collapse: collapse;
        }
        .content-table td {
            padding: 4px 4px;
            font-size: 14px;
            vertical-align: top;
        }
        .content-table .label-col {
            font-weight: bold;
            width: 25%;
        }
        .content-table .value-col {
            width: 45%;
            word-break: break-word; /* Allow words to break to wrap */
            white-space: normal; /* Ensure text wraps */
            height: auto;
        }
        .content-table .qr-col {
            width: 30%;
            text-align: center;
            vertical-align: top;
        }
        .footer-cell {
            padding-top: 10px !important;
        }
        .footer-note {
            font-style: italic;
            font-size: 10px;
            text-align: center;
            width: 95%;
        }
    </style>
</head>
<body>
    <?php
        $route = $document['finalized_route'] ? json_decode($document['finalized_route'], true) : [];
        $routeNames = array_column($route, 'name');
        $routeString = !empty($routeNames) ? implode(' -> ', $routeNames) : 'N/A';
        
        $guestInfo = json_decode($document['guest_info'], true) ?: [];
        $createdAt = new \DateTime($document['created_at']);
        
        $details = [
            'Tracking Number:' => htmlspecialchars($document['tracking_code']),
            'Date and Time Submitted:' => htmlspecialchars($createdAt->format("F d, Y h:i A")),
            'Document Title:' => htmlspecialchars($document['title']),
            'Purpose:' => htmlspecialchars($document['purpose_name'] ?? 'N/A'),
            'Submitted By:' => htmlspecialchars($guestInfo["name"] ?? "N/A (Guest)"),
            'Email:' => htmlspecialchars($guestInfo["email"] ?? "N/A"),
            'District:' => htmlspecialchars($document['district'] ?? 'N/A'),
            'Unit/Department:' => htmlspecialchars($document['department'] ?? 'N/A'),
        ];
        $rowCount = count($details);
        
        // Construct the tracking URL based on the current host.
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'];
        $trackingUrl = $protocol . $host . '/track?codes=' . urlencode($document['tracking_code']);
        
        // Base64 encode the logo for reliable DomPDF rendering
        $logoPath = BASE_PATH . '/public/images/DepEd Seal.png';
        $logoData = '';
        if (file_exists($logoPath)) {
            $logoData = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
        }

        function renderFormContent($details, $rowCount, $qrCodeBase64, $trackingUrl, $logoData) {
            echo '
            <div class="header">
                <img src="' . $logoData . '" alt="DepEd Logo">
                <p>Republic of the Philippines</p>
                <p>Department of Education</p>
                <p>Region X - Northern Mindanao</p>
                <p class="division">SCHOOLS DIVISION OF ILIGAN CITY</p>
            </div>
            <h2 class="title">Document Tracking Form</h2>
            
            <table class="content-table">
                <tbody>';
                    $firstRow = true;
                    foreach ($details as $label => $value) {
                        echo '<tr>';
                        echo '<td class="label-col">' . $label . '</td>';
                        echo '<td class="value-col">' . $value . '</td>';
                        if ($firstRow) {
                            echo '<td class="qr-col" rowspan="' . $rowCount . '">
                                      <img src="data:image/png;base64,' . $qrCodeBase64 . '" style="width: 125px; height: 125px;">
                                  </td>';
                            $firstRow = false;
                        }
                        echo '</tr>';
                    }
            echo '</tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" class="footer-cell">
                            <p class="footer-note">
                                This is to acknowledge the receipt of your document(s) which has been submitted to the Document Tracking System and will be processed by the respective office.<br>Please use your tracking number or QR code for follow up in the tracking page.
                                <br>You can track your document here: <a href="' . htmlspecialchars($trackingUrl) . '" target="_blank">' . htmlspecialchars($trackingUrl) . '</a>
                            </p>
                        </td>
                    </tr>
                </tfoot>
            </table>';
        }
    ?>

    <div class="form-container">
        <?php renderFormContent($details, $rowCount, $qrCodeBase64, $trackingUrl, $logoData); ?>
        <div class="cut-line"></div>
        <?php renderFormContent($details, $rowCount, $qrCodeBase64, $trackingUrl, $logoData); ?>
    </div>
</body>
</html>
