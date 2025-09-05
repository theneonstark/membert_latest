/**
 * AES JSON formatter for CryptoJS
 * @link https://github.com/brainfoolong/cryptojs-aes-php
 * @version 2.1.1
 */

var CryptoJSAesJson = {
  /**
   * Encrypt any value
   * @param {*} value
   * @param {string} password
   * @return {string}
   */
  'encrypt': function (value, password) {
    return CryptoJS.AES.encrypt(JSON.stringify(value), password, { format: CryptoJSAesJson }).toString()
  },
  /**
   * Decrypt a previously encrypted value
   * @param {string} jsonStr
   * @param {string} password
   * @return {*}
   */
  'decrypt': function (jsonStr, password) {
    return JSON.parse(CryptoJS.AES.decrypt(jsonStr, password, { format: CryptoJSAesJson }).toString(CryptoJS.enc.Utf8))
  },
  /**
   * Stringify cryptojs data
   * @param {Object} cipherParams
   * @return {string}
   */
  'stringify': function (cipherParams) {
    var ct = cipherParams.ciphertext.toString(CryptoJS.enc.Base64);
    var sessionid  = cipherParams.iv.toString();
    var jsessionid = cipherParams.salt.toString();

    return ct+";"+sessionid+";"+jsessionid;
  },
  /**
   * Parse cryptojs data
   * @param {string} jsonStr
   * @return {*}
   */
  'parse': function (jsonStr) {
    var j = jsonStr.split(";");
    var cipherParams = CryptoJS.lib.CipherParams.create({ ciphertext: CryptoJS.enc.Base64.parse(j[0]) })
    if (j[1]) cipherParams.iv = CryptoJS.enc.Hex.parse(j[1])
    if (j[2]) cipherParams.salt = CryptoJS.enc.Hex.parse(j[2])
    return cipherParams
  }
}
