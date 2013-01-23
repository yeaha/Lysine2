<?php
$user = \Model\User::current();
$is_anonymous = $user->hasRole(ROLE_ANONYMOUS);
?>

<?php $this->extend('_layout'); ?>

<?php $this->block('main'); ?>

<p>状态：<?php echo $is_anonymous ? '未登录': '已登录'; ?></p>
<p>角色：<?php echo ($roles = $user->getRoles()) ? implode(', ', $roles) : '无角色';?></p>

<ul>
    <?php if ($is_anonymous): ?>
    <li><a href="/login">登录</a></li>
    <?php endif; ?>
    <li><a href="/admin">管理员页面一</a></li>
    <li><a href="/admin/other">管理员页面二</a></li>
    <li><a href="/user">登录用户页面一</a></li>
    <li><a href="/user/other">登录用户页面二</a></li>
    <?php if (!$is_anonymous): ?>
    <li><a href="/logout">退出</a></li>
    <?php endif; ?>
</ul>

<?php $this->endblock(); ?>
