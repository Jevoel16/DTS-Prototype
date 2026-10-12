const crypto = require('crypto');
const data = [1, "", "Submitted", "2026-06-25T11:28:00+08:00", "genesis_hash", "fdf6e5c03047a64033e27c194d4f7e360e1924e2757345dc9688e37b7b1e46ff", "U1lTVEVNX1NJRzpTdWJtaXR0ZWR8ZmRmNmU1YzAzMDQ3YTY0MDMzZTI3YzE5NGQ0ZjdlMzYwZTE5MjRlMjc1NzM0NWRjOTY4OGUzN2I3YjFlNDZmZg=="];
console.log(JSON.stringify(data));
console.log(crypto.createHash('sha256').update(JSON.stringify(data)).digest('hex'));
