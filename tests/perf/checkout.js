// k6 load test (CLAUDE.md §7): browse category -> search -> product -> add to cart (signed in) -> COD order.
// Run through scripts/perf.sh, which passes BASE_URL, PERF_PASSWORD and PRODUCTS ("sku|url-key.html,...").
// Pages are plain GETs (Full Page Cache); cart and checkout use the customer REST API, one customer per VU.
import http from 'k6/http';
import { check, fail, sleep } from 'k6';

const BASE_URL = __ENV.BASE_URL || 'https://technest.test';
const PASSWORD = __ENV.PERF_PASSWORD;
const VUS = Number(__ENV.VUS || 20);
const PRODUCTS = (__ENV.PRODUCTS || '').split(',').filter(Boolean).map((entry) => {
    const [sku, url] = entry.split('|');
    return { sku, url };
});

const CATEGORIES = ['/laptops.html', '/phones.html', '/pcs.html', '/accessories.html', '/accessories/mice.html'];
const SEARCHES = ['laptop', 'iphone', 'mouse', 'samsung', 'gaming'];
const ADDRESS = {
    firstname: 'Perf',
    lastname: 'TechNest',
    street: ['1 Trang Tien'],
    city: 'Ha Noi',
    region_id: 1191,
    postcode: '100000',
    country_id: 'VN',
    telephone: '0912345678',
};

const STEPS = ['category', 'search', 'product', 'cart', 'add to cart', 'shipping', 'place order'];

export const options = {
    insecureSkipTLSVerify: true,
    setupTimeout: '120s',
    stages: [
        { duration: '20s', target: VUS },
        { duration: '40s', target: VUS },
        { duration: '10s', target: 0 },
    ],
    thresholds: {
        http_req_failed: ['rate==0'],
        checks: ['rate==1'],
        // TTFB of storefront pages with a warm cache
        'http_req_waiting{type:page}': ['p(95)<500'],
        // Report only (always pass): per-step timings in the summary
        ...Object.fromEntries(STEPS.map((step) => [`http_req_duration{name:${step}}`, ['max>=0']])),
    },
    summaryTrendStats: ['avg', 'med', 'p(90)', 'p(95)', 'max'],
};

const JSON_HEADERS = { 'Content-Type': 'application/json' };

function email(vu) {
    return `perf-${String(vu).padStart(2, '0')}@technest.test`;
}

function api(method, path, body, token, name, params = {}) {
    const headers = token ? { ...JSON_HEADERS, Authorization: `Bearer ${token}` } : JSON_HEADERS;
    return http.request(method, `${BASE_URL}/rest/V1${path}`, body ? JSON.stringify(body) : null, {
        headers,
        tags: { type: name === 'setup' ? 'setup' : 'api', name },
        ...params,
    });
}

function page(path, name) {
    const res = http.get(`${BASE_URL}${path}`, { tags: { type: 'page', name } });
    check(res, { [`${name} 200`]: (r) => r.status === 200 });
    return res;
}

// One customer per VU (created once, re-used on later runs) and the cache warmed up before measuring
export function setup() {
    if (!PASSWORD || PRODUCTS.length === 0) {
        fail('PERF_PASSWORD and PRODUCTS are required: run scripts/perf.sh');
    }
    const tokens = [];
    for (let vu = 1; vu <= VUS; vu++) {
        const created = api('POST', '/customers', {
            customer: {
                email: email(vu),
                firstname: 'Perf',
                lastname: `VU${vu}`,
                custom_attributes: [{ attribute_code: 'phone_number', value: ADDRESS.telephone }],
            },
            password: PASSWORD,
        }, null, 'setup', { responseCallback: http.expectedStatuses(200, 400) }); // 400: created on an earlier run
        if (created.status !== 200 && !String(created.body).includes('already exists')) {
            fail(`cannot create ${email(vu)}: ${created.status} ${created.body}`);
        }
        const login = api('POST', '/integration/customer/token', { username: email(vu), password: PASSWORD }, null, 'setup');
        if (login.status !== 200) {
            fail(`cannot sign in ${email(vu)}: ${login.status} ${login.body}`);
        }
        tokens.push(login.json());
    }
    const warm = [...CATEGORIES, ...SEARCHES.map((q) => `/catalogsearch/result/?q=${q}`), ...PRODUCTS.map((p) => `/${p.url}`)];
    warm.forEach((path) => http.get(`${BASE_URL}${path}`, { tags: { type: 'warmup' } }));
    return { tokens };
}

export default function (data) {
    const token = data.tokens[(__VU - 1) % data.tokens.length];
    const product = PRODUCTS[(__VU + __ITER) % PRODUCTS.length];

    page(CATEGORIES[__ITER % CATEGORIES.length], 'category');
    sleep(1 + Math.random());
    page(`/catalogsearch/result/?q=${SEARCHES[(__VU + __ITER) % SEARCHES.length]}`, 'search');
    sleep(1 + Math.random());
    page(`/${product.url}`, 'product');
    sleep(1 + Math.random());

    const cart = api('POST', '/carts/mine', null, token, 'cart');
    const added = api('POST', '/carts/mine/items', {
        cartItem: { sku: product.sku, qty: 1, quote_id: String(cart.json()) },
    }, token, 'add to cart');
    check(added, { 'add to cart 200': (r) => r.status === 200 });

    const shipping = api('POST', '/carts/mine/shipping-information', {
        addressInformation: {
            shipping_address: ADDRESS,
            billing_address: ADDRESS,
            shipping_carrier_code: 'technest_regional',
            shipping_method_code: 'regional',
        },
    }, token, 'shipping');
    check(shipping, { 'shipping 200': (r) => r.status === 200 });

    const order = api('POST', '/carts/mine/payment-information', {
        paymentMethod: { method: 'cashondelivery' },
        billingAddress: ADDRESS,
    }, token, 'place order');
    check(order, { 'place order 200': (r) => r.status === 200 && Number(r.body) > 0 });
    sleep(1);
}
