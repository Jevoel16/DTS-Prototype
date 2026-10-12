const fetch = require('node-fetch'); // wait, the script uses built-in fetch (Node 18+)
async function test() {
    const res = await fetch('http://localhost:8000/submit-document', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            guest_name: 'Test',
            guest_email: 'test@example.com',
            guest_phone: '09123456789',
            district: 'East I District',
            department: 'Accounting',
            title: 'Test',
            purpose_id: 1
        })
    });
    console.log(res.status);
    console.log(res.headers.get('location'));
    console.log(await res.text());
}
test();
