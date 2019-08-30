<?php

namespace buddysoft\sms\utils;

class PseudoConverter
{

  /**
   * 判断某个号码是否是用来测试的 pseudo 号码
   *
   * @param string $mobile 检查是否是 fakeNumber 的源号码
   * @param array $pseudos 测试号码配置，格式参考 SmsController 类说明
   * @return 是 pseudo 号码时返回对应号码，否则返回源号码
   */
  public static function convert(string $mobile, array $pseudos) {
    foreach ($pseudos as $pseudo) {
      if ($pseudo['fakeNumber'] == $mobile) {
        return $pseudo['sendNumber'];
      }
    }

    return $mobile;
 }
}

?>