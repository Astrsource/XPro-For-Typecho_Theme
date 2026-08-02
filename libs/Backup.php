<?php
declare(strict_types=1);
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 主题配置备份工具
 *
 * @package XPro
 */
class Backup
{
    /**
     * 输出备份面板（含 POST 处理与按钮渲染）
     *
     * @return void
     */
    public static function echoBackup(): void
    {
        self::handlePost();

        $name = self::getThemeName();
        if ($name === '') {
            echo '<p style="color:#ff4d4f;">无法识别当前主题名称</p>';
            return;
        }

        $db = Typecho_Db::get();
        $backupName = 'theme:' . $name . 'bf';
        $hasBackup = (bool) $db->fetchRow(
            $db->select()->from('table.options')->where('name = ?', $backupName)
        );

        $options = Helper::options();
        $actionUrl = Typecho_Common::url('options-theme.php', $options->adminUrl);

        ?>
        <div class="backup-panel" style="margin-bottom:24px;padding:20px;border:1px solid #e8e8e8;border-radius:8px;background:#fafafa;">
            <h3 style="margin:0 0 16px;font-size:16px;color:#262626;font-weight:600;">主题数据备份</h3>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <button type="button" class="backup-btn primary" onclick="backupAction('backup')">📦 备份当前设置</button>
                <button type="button" class="backup-btn" onclick="backupAction('restore')" <?php if (!$hasBackup) { echo 'disabled'; } ?>>↩️ 还原设置</button>
                <button type="button" class="backup-btn danger" onclick="backupAction('delete')" <?php if (!$hasBackup) { echo 'disabled'; } ?>>🗑️ 删除备份</button>
            </div>
            <p style="margin:12px 0 0;font-size:12px;color:<?php echo $hasBackup ? '#52c41a' : '#999'; ?>;">
                <?php echo $hasBackup ? '✓ 已检测到备份数据，可进行还原或删除操作。' : '暂无备份数据，请先执行备份。'; ?>
            </p>
        </div>
        <style>
            .backup-btn { padding:6px 16px;border:1px solid #d9d9d9;background:#fff;border-radius:4px;cursor:pointer;font-size:13px;color:#595959;transition:all .2s;line-height:1.5; }
            .backup-btn:hover:not(:disabled) { border-color:#1890ff;color:#1890ff; }
            .backup-btn.primary { background:#1890ff;border-color:#1890ff;color:#fff; }
            .backup-btn.primary:hover:not(:disabled) { background:#40a9ff;border-color:#40a9ff; }
            .backup-btn.danger { background:#ff4d4f;border-color:#ff4d4f;color:#fff; }
            .backup-btn.danger:hover:not(:disabled) { background:#ff7875;border-color:#ff7875; }
            .backup-btn:disabled { opacity:0.5;cursor:not-allowed; }
        </style>
        <script>
            (function(){
                var actionUrl = <?php echo json_encode($actionUrl, JSON_UNESCAPED_SLASHES); ?>;
                window.backupAction = function(type) {
                    var msgs = {
                        backup: '确定要备份当前模板设置吗？',
                        restore: '警告：还原操作将覆盖当前主题配置，确定继续？',
                        delete: '确定要删除已保存的备份数据吗？此操作不可恢复！'
                    };
                    if (!confirm(msgs[type])) return;
                    
                    var form = document.createElement('form');
                    form.method = 'post';
                    form.action = actionUrl;
                    form.style.display = 'none';
                    
                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'backup_action';
                    input.value = type;
                    form.appendChild(input);
                    
                    document.body.appendChild(form);
                    form.submit();
                };
            })();
        </script>
        <?php
    }

    /**
     * 从 themeUrl 中提取当前主题目录名
     *
     * @return string 主题目录名，失败时返回空字符串
     */
    private static function getThemeName(): string
    {
        $themeUrl = (string) Helper::options()->themeUrl;
        $parts = explode('/themes/', $themeUrl);
        if (count($parts) < 2) {
            return '';
        }
        return explode('/', $parts[1])[0] ?? '';
    }

    /**
     * 处理备份相关的 POST 请求（backup / restore / delete）
     *
     * @return void
     */
    private static function handlePost(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['backup_action'])) {
            return;
        }

        $action = (string) $_POST['backup_action'];
        $name = self::getThemeName();
        if ($name === '') {
            self::notify('无法识别当前主题名称', 'error');
            return;
        }

        $db = Typecho_Db::get();
        $backupName = 'theme:' . $name . 'bf';
        $themeName = 'theme:' . $name;

        try {
            switch ($action) {
                case 'backup':
                    $current = $db->fetchRow(
                        $db->select('value')->from('table.options')->where('name = ?', $themeName)
                    );
                    $value = $current['value'] ?? '';

                    $exists = $db->fetchRow(
                        $db->select()->from('table.options')->where('name = ?', $backupName)
                    );

                    if ($exists) {
                        $db->query(
                            $db->update('table.options')
                                ->rows(['value' => $value])
                                ->where('name = ?', $backupName)
                        );
                        self::notify('备份数据已更新');
                    } else {
                        $db->query(
                            $db->insert('table.options')
                                ->rows([
                                    'name' => $backupName,
                                    'user' => '0',
                                    'value' => $value
                                ])
                        );
                        self::notify('数据备份成功');
                    }
                    break;

                case 'restore':
                    $backup = $db->fetchRow(
                        $db->select('value')->from('table.options')->where('name = ?', $backupName)
                    );
                    if (!empty($backup)) {
                        $db->query(
                            $db->update('table.options')
                                ->rows(['value' => $backup['value']])
                                ->where('name = ?', $themeName)
                        );
                        self::notify('数据恢复成功');
                    } else {
                        self::notify('数据库中没有当前主题的备份数据', 'error');
                    }
                    break;

                case 'delete':
                    $exists = $db->fetchRow(
                        $db->select()->from('table.options')->where('name = ?', $backupName)
                    );
                    if ($exists) {
                        $db->query(
                            $db->delete('table.options')->where('name = ?', $backupName)
                        );
                        self::notify('备份数据已删除');
                    } else {
                        self::notify('数据库中没有当前主题的备份数据', 'error');
                    }
                    break;
            }
        } catch (Exception $e) {
            self::notify('操作失败: ' . $e->getMessage(), 'error');
        }
    }

    /**
     * 在前端显示通知消息并跳转刷新页面
     *
     * @param string $message 通知消息文本
     * @param string $type    消息类型（success 为成功，error 为错误）
     * @return void
     */
    private static function notify(string $message, string $type = 'success'): void
    {
        $options = Helper::options();
        $url = Typecho_Common::url('options-theme.php', $options->adminUrl);
        $color = $type === 'error' ? '#ff4d4f' : '#52c41a';
        $borderColor = $type === 'error' ? '#ff4d4f' : '#52c41a';
        
        echo "<script>
            document.addEventListener('DOMContentLoaded', function(){
                var div = document.createElement('div');
                div.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;padding:12px 20px;background:#fff;border-radius:4px;box-shadow:0 4px 12px rgba(0,0,0,0.15);font-size:14px;color:" . $color . ";border-left:4px solid " . $borderColor . ";transition:opacity 0.5s;';
                div.textContent = " . json_encode($message, JSON_UNESCAPED_UNICODE) . ";
                document.body.appendChild(div);
                setTimeout(function(){
                    div.style.opacity = '0';
                    setTimeout(function(){ window.location.href = '" . $url . "'; }, 500);
                }, 1800);
            });
        </script>";
        exit;
    }
}
