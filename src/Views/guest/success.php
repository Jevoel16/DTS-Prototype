<div class="bg-white dark:bg-gray-800 shadow-lg rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 max-w-2xl mx-auto">
    <div class="bg-success text-white p-6 text-center">
        <h1 class="text-2xl font-bold">Submission Successful!</h1>
    </div>
    
    <div class="p-8 text-center">
        <p class="text-xl text-gray-700 dark:text-gray-300 mb-6">Your document request has been received.</p>
        
        <hr class="my-6 border-gray-200 dark:border-gray-700">

        <div class="bg-yellow-50 dark:bg-yellow-900 border-l-4 border-yellow-400 p-4 mb-8 text-left rounded-r">
            <h4 class="text-yellow-800 dark:text-yellow-200 font-bold text-lg mb-2">Action Required!</h4>
            <p class="text-yellow-700 dark:text-yellow-300">
                You <strong class="underline">MUST</strong> print the official Document Tracking Form. This printed form must be submitted to the Records Office along with your documents to begin the process.
            </p>
        </div>

        <a href="/documents/<?= urlencode($tracking_code) ?>/print-tracking-form" target="_blank" class="bg-primary hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg transition duration-200 shadow-md inline-flex items-center mb-8">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
            Print Document Tracking Form
        </a>

        <div class="mb-8">
            <p class="text-gray-600 dark:text-gray-400 mb-4">For your reference, here is your tracking information:</p>
            <div class="inline-block p-4 border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700">
                <div class="mb-4 flex justify-center bg-white p-2 rounded">
                    <img src="<?= $qrCodeImage ?>" alt="QR Code for <?= htmlspecialchars($tracking_code) ?>" class="w-48 h-48">
                </div>
                <p class="text-2xl font-mono font-bold text-gray-800 dark:text-gray-200"><?= htmlspecialchars($tracking_code) ?></p>
            </div>
        </div>

        <div class="flex flex-col sm:flex-row justify-center space-y-3 sm:space-y-0 sm:space-x-4 mt-6">
            <a href="/track?codes=<?= urlencode($tracking_code) ?>" class="bg-teal-500 hover:bg-teal-600 text-white font-semibold py-2 px-6 rounded shadow transition duration-200">
                Track Your Document Status
            </a>
            <a href="/" class="bg-gray-500 hover:bg-accent-2 text-white font-semibold py-2 px-6 rounded shadow transition duration-200">
                Submit Another Request
            </a>
        </div>
    </div>
</div>

<style>
    @media print {
        body * {
            visibility: hidden;
        }
        .max-w-2xl, .max-w-2xl * {
            visibility: visible;
        }
        .max-w-2xl {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            border: none;
            box-shadow: none;
        }
        button, a, .fixed {
            display: none !important;
        }
    }
</style>
