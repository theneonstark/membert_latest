const potential_urls = [];
if (window.location.host == 'prime') {
    potential_urls.push(...[{
            url: 'https://aeps.in.ngrok.io',
            port: 11100
        }
    ]);
} else {
    var port = 11100;
    for (; port <= 11120; port++) {
        potential_urls.push({
            url: `http://127.0.0.1:${port}`,
            port
        });

        potential_urls.push({
            url: `https://127.0.0.1:${port}`,
            port
        });
    }
}

function scan_all_rd_services(scan_index = 0, network_err_count = 0) {
    if (potential_urls.length == scan_index || network_err_count >= 7) {
        $.cookie("RDSLS", JSON.stringify(STOCK.RD_SERVICES));
        FLAG.RD_SERVICE_SCAN_DONE = true;
        return;
    }

    invoke_request({
        type: 'RDSERVICE',
        url: potential_urls[scan_index].url,
        success(data) {
            if (!$.isXMLDoc(data)) {
                data = $.parseXML(data);
            }

            var deviceStatus = $(data).find('RDService').attr('status');
            var deviceInfo = $(data).find('RDService').attr('info');

            var deviceInfoPath = $(data).find('Interface[id="DEVICEINFO"]').attr('path');
            var deviceCapturePath = $(data).find('Interface[id="CAPTURE"]').attr('path');

            if (/morpho/i.test(deviceInfo)) {
                // Device is Morpho
                deviceInfoPath = /\/getDeviceInfo$/.test(deviceInfoPath) ? '/getDeviceInfo' : '/rd/info';
                deviceCapturePath = /\/capture$/.test(deviceCapturePath) ? '/capture' : '/rd/capture';
            }

            var error = 0;
            if (deviceStatus != 'READY') {
                error = 1;
            }

            STOCK.RD_SERVICES.push({
                port: potential_urls[scan_index].port,
                url: potential_urls[scan_index].url,
                deviceInfo,
                deviceInfoPath,
                deviceCapturePath
            });

            scan_all_rd_services(++scan_index);
        },
        error() {
            scan_all_rd_services(++scan_index, ++network_err_count);
        }
    });
}

function aadhaar_capture(type) {
    if (!FLAG.RD_SERVICE_SCAN_DONE) {
        setTimeout(() => {
            aadhaar_capture(type);
        }, 1000);
        return;
    }

    did = 'RDSC';
    header = 'Capture Biometrics';
    $.alert({
        title: '',
        content: ' ',
        draggable: true,
        containerFluid: true,
        scrollToPreviousElement: true,
        columnClass: 'col-md-6 col-md-offset-3',
        scrollToPreviousElementAnimate: true,
        onOpenBefore: function() {
            this.buttons.cancel.hide();
            this.buttons.refreshList.hide();

            this.setContent(`
                <div class="text-center">
                    <div class="text-center" style="margin: 20px;">
                        <img src="`+loading+`">
                    </div>
                    <div style="font-weight: 700;" class="text-center">Please wait. Looking for RD Services</div>
                </div>
            `);
        },
        onOpen: function() {
            var errorMsg = `
                <div class="row">
                    <div class="col-md-8" style="border-right: 1px solid rgb(0,0,0,0.5);">
                        <div class="text-center" style="margin: 20px;">
                            <p style="color:red;font-size:18px;">Unable to auto-detect the device</p>
                        </div>
                        <div>Follow the below steps to resolve the RD Service Error</div><br>
                        <div style="font-size: 14px;margin-bottom:5px;"><strong>Step 1.</strong> Open Chrome browser in your laptop/desktop and type</div>
                        <div style="font-size: 14px;margin-bottom:5px;background-color:#ececec;border-radius: 5px;padding:4px;text-align: center;"><span>chrome://flags/#allow-insecure-localhost</span></div>
                        <div style="font-size: 14px;margin-bottom:5px;"><strong>Step 2.</strong> Click on Enabled to <em>Allow invalid certificates for resources loaded from local host</em> and then click on <em>RELAUNCH NOW</em> button</div>
                        <div style="font-size: 14px;margin-bottom:5px;"><strong>Step 3.</strong> Please ensure that RD Service is running</div>
                        <div style="font-size: 14px;margin-bottom:5px;"><strong>Step 4.</strong> Check that device is being detected in Device Manager. If not, then update the device drivers.</div>
                        <div style="font-size: 14px;margin-bottom:5px;"><strong>Step 5.</strong> Restart your laptop/desktop and follow the above mentioned steps again.</div>
                    </div>
                    <div class="col-md-4">
                        <div style="font-size:12px;text-align: center">
                            <span class="btn btn-grey" style="font-size: 11px">All Biometric Devices Supported</span>
                            <br><br>
                            <span>Download RD Services of popular biometric device brands from the below links -</span>
                        </div>
                        <br>
                        <div class="text-center">
                            <a class="btn btn-primary" style="font-size: 12px;width: 80px;margin: 5px;" target="_blank" href="http://download.mantratecapp.com/Forms/DownLoadFiles">Mantra</a>
                            <a class="btn btn-primary" style="font-size: 12px;width: 80px;margin: 5px;" target="_blank" href="https://www.instantpay.in/static/download/rdservice/Windows_RD_Service_V2.0.1.34_HTTP.zip">Morpho</a>
                            <a class="btn btn-primary" style="font-size: 12px;width: 80px;margin: 5px;" target="_blank" href="https://www.acpl.in.net/RdService.html">Startek</a>
                            <a class="btn btn-primary" style="font-size: 12px;width: 80px;margin: 5px;" target="_blank" href="https://pbrdms.precisionbiometric.co.in/RDService">Precision</a>
                            <a class="btn btn-primary" style="font-size: 12px;width: 80px;margin: 5px;" target="_blank" href="https://www.secugenindia.com/download">SecuGen</a>
                        </div>
                    </div>
                </div>
            `;

            if (STOCK.RD_SERVICES.length == 0) {
                this.setContent(errorMsg);
                this.buttons.refreshList.show();
                this.buttons.cancel.show();
                // callback({
                //     error: -1,
                //     data: {}
                // });
                return;
            }

            discover_device((data, rd_error_bag) => {
                if (rd_error_bag) {
                    if (rd_error_bag.length != 0) {
                        var rd_services_error_html = '';
                        rd_services_error_html += '<center><div>We are getting the following error(s) from the RD Service(s) installed on your system</div></center>';
                        rd_services_error_html += '<table class="table table-striped ordertable">';
                        rd_services_error_html += '  <tbody>';
                        rd_services_error_html += '    <tr><td></td><td></td><td></td></tr>';


                        rd_error_bag.forEach(rd_error => {
                            console.log(rd_error);
                            rd_services_error_html += `<tr><td colspan="2"><b>${rd_error.data.deviceInfo}</b></td><td style="color: red">${rd_error.data.deviceStatus}</td></tr>`;
                        });

                        rd_services_error_html += '  </tbody>';
                        rd_services_error_html += '<table>';

                        this.setContent(rd_services_error_html);
                        this.buttons.cancel.show();
                        this.buttons.refreshList.show();
                        // callback({
                        //     error: 1,
                        //     data: {}
                        // });
                    } else {
                        // network error with any one rd service
                        this.setContent(errorMsg);
                        this.buttons.refreshList.show();
                        this.buttons.cancel.show();
                        // data.biometric_devlog = biometric_devlog;
                        // callback({
                        //     error: -1,
                        //     data: {}
                        // });
                    }
                    return;
                }

                var { error } = data;
                if (error == 0) {
                    this.setContent(`
                        <br>
                        <img src="`+gif+`" style="display: block;margin: auto;font-size:13px;" width="160px" height="160px">
                        <br>
                        <center><strong>Place your thumb/finger on biometric device</strong></center>
                    `);

                    var { data: { url, deviceCapturePath } } = data;
                    capture_fingerprint(url + deviceCapturePath, type, data => {
                        console.log(data);
                        if (error != 0) {
                            this.setContent(`
                                <div class="text-center">
                                    <div style="font-weight: 700;" class="text-center">${errInfo}</div>
                                </div>
                            `);
                            this.buttons.cancel.show();
                        } else {
                            this.close();
                        }
                    });
                }
            });
        },
        buttons: {
            refreshList: {
                text: 'Refresh Device List',
                action() {
                    this.setContent(`
                        <div class="text-center">
                            <div class="text-center" style="margin: 20px;">
                                <img src="`+loading+`">
                            </div>
                            <div style="font-weight: 700;" class="text-center">Please wait. Refreshing Device List...</div>
                        </div>
                    `);
                    this.buttons.refreshList.hide();
                    this.buttons.cancel.hide();
                    FETCH_RD_SERVICE_LIST(true);
                    var interval_id = setInterval(() => {
                        if (FLAG.RD_SERVICE_SCAN_DONE) {
                            clearInterval(interval_id);
                            this.setContent(`
                                <div class="text-center">
                                    <div style="font-weight: 700;" class="text-center">Device list has been refreshed successfully. Please try now.</div>
                                </div>
                            `);
                            this.buttons.cancel.show();
                        }
                    }, 1000);
                    return false;
                }
            },
            cancel: {
                text: 'close',
                btnClass: 'btn-primary'
            }
        }
    });
}

function discover_device(callback, rd_index = 0, rd_error_bag = []) {
    if (rd_index == STOCK.RD_SERVICES.length) {
        callback(null, rd_error_bag);
        return;
    }

    invoke_request({
        type : 'RDSERVICE',
        url  : STOCK.RD_SERVICES[rd_index].url,
        success(data) {
            if (!$.isXMLDoc(data)) {
                data = $.parseXML(data);
            }

            var deviceStatus = $(data).find('RDService').attr('status');
            var deviceInfo = $(data).find('RDService').attr('info');

            var deviceInfoPath = $(data).find('Interface[id="DEVICEINFO"]').attr('path');
            var deviceCapturePath = $(data).find('Interface[id="CAPTURE"]').attr('path');

            if (/morpho/i.test(deviceInfo)) {
                // Device is Morpho
                deviceInfoPath = /\/getDeviceInfo$/.test(deviceInfoPath) ? '/getDeviceInfo' : '/rd/info';
                deviceCapturePath = /\/capture$/.test(deviceCapturePath) ? '/capture' : '/rd/capture';
            }

            var error = 0;
            if (deviceStatus != 'READY') {
                error = 1;
                rd_error_bag.push({
                    error,
                    data: {
                        port: STOCK.RD_SERVICES[rd_index].port,
                        url: STOCK.RD_SERVICES[rd_index].url,
                        deviceStatus,
                        deviceInfo,
                        deviceInfoPath,
                        deviceCapturePath
                    }
                });
                discover_device(callback, ++rd_index, rd_error_bag);
                return;
            }

            callback({
                error,
                data: {
                    port: STOCK.RD_SERVICES[rd_index].port,
                    url: STOCK.RD_SERVICES[rd_index].url,
                    deviceStatus,
                    deviceInfo,
                    deviceInfoPath,
                    deviceCapturePath
                }
            });
        },
        error() {
            rd_error_bag.push({
                error: -1,
                data: {}
            });
            FETCH_RD_SERVICE_LIST(true);
            var interval_id = setInterval(() => {
                if (FLAG.RD_SERVICE_SCAN_DONE) {
                    clearInterval(interval_id);
                    discover_device(callback);
                }
            }, 1000);
        }
    });
}

function capture_fingerprint(url, type, callback) {
    // S  - Staging
    // PP - Pre-Production
    // P  - Production
    var env  = 'P';
    var data = `<?xml version="1.0"?><PidOptions ver="1.0"><Opts fCount="1" fType="2" iCount="0" pCount="0" format="0" pidVer="2.0" timeout="10000" posh="UNKNOWN" env="${env}" /><CustOpts></CustOpts></PidOptions>`;

    if(type == "kyc"){
        var data = `<PidOptions ver=\"1.0\"><Opts env=\"P\" fCount=\"1\" fType=\"2\" iCount=\"0\" format=\"0\" pidVer=\"2.0\" timeout=\"15000\" wadh=\"E0jzJ/P8UopUHAieZn8CKqS4WPMi5ZSYXgfnlfkWjrc=\" posh=\"UNKNOWN\" /></PidOptions>`;
    }

    invoke_request({
        type: 'CAPTURE',
        url,
        data,
        success(data) {
            var newdata = data;
            if (!$.isXMLDoc(data)) {
                newdata = $.parseXML(data);
            }
            
            var errCode = $(newdata).find('Resp').attr('errCode');
            var errInfo = $(newdata).find('Resp').attr('errInfo');
            
            console.log(data, newdata, errCode, errInfo);

            var error;

            if (errCode == 0) {
                error = 0;
                if (!$.isXMLDoc(data)) {
                    $('[name="biodata"]').val(data);
                }else{
                    $('[name="biodata"]').val("<PidData>"+$(newdata).find('PidData').html()+"</PidData>");
                }
                $(type).trigger("click");
            } else {
                error = 1;
            }
        
            callback({
                error,
                data: data
            });
        },
        error() {
            var errInfo = 'Fingerprint capture Failed';
            callback({
                error: -1,
                data: {
                    errInfo
                }
            });
        }
    });
}

function invoke_request(options) {
    let { type, url, data, success, error } = options;
    if (!type || !url || !success || !error) {
        throw Error('call to invoke_request is not valid');
    }

    $.support.cors = true;

    $.ajax({
        crossDomain: true,
        // contentType: 'text/xml; charset=utf-8',
        processData: false,
        cache: false,
        type,
        url,
        data,
        success,
        error
    });
}