<?php $this->extend('_layout'); ?>

<?php $this->block('main'); ?>
<h1>用户登录</h1>

<?php if ($message = $this->get('message')): ?>
<p><?php echo $message; ?></p>
<?php endif; ?>

<form method="post">
    Email: <input type="email" name="email"/>
    密码: <input type="password" name="passwd"/>
    <button type="submit">提交</button>
</form>

<p>普通用户：user@example.com 密码：user</p>
<p>管理员：admin@example.com 密码：admin</p>
<?php $this->endblock(); ?>
