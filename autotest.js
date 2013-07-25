var page = require('webpage').create(),
    system = require('system'),
    address, output;
address = 'http://www.yotpo-prestashop-test.com/';
output = 'test_output/lalalal.png';
page.viewportSize = { width: 1024, height: 768 };
page.open(address, function (status) {
    if (status !== 'success') {
        console.log('Unable to load the address!');
        phantom.exit();
    } else {
        console.log('Setting timeout');
        window.setTimeout(function () {
            console.log('timeout riched');
            page.render(output);
            phantom.exit();
        }, 200);
        console.log('bye');
    }
});