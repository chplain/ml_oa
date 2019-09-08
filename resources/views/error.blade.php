<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<meta content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0;" name="viewport" />
	<title>提示</title>
</head>

<style type="text/css">
	* {
	    margin: 0;
	    padding: 0;
	}

	body {
	    font-family: "Hiragino Sans GB", "Microsoft Yahei", "SimSun", Arial, "Helvetica Neue", Helvetica";
	    background: #f8f9fc;
	    text-align: center;
	}

	.i_error {
	    position: relative;
	    width: 90%;
	    margin: 15px auto;
	}

	img[Attributes Style] {
	    width: 100%;
	}

	.i_logo {
	    position: absolute;
	    top: 24.8394%;
	    left: 9.785933%;
	    width: 16.819572%;
	}

	.tip {
	    margin-top: 30px;
	    font-size: 24px;
	    line-height: 24px;
	    text-align: center;
	    color: #333;
	}

	p {
	    display: block;
	    margin-block-start: 1em;
	    margin-block-end: 1em;
	    margin-inline-start: 0px;
	    margin-inline-end: 0px;
	}
</style>

<body>
	<div class="i_error">
		<img src="{{ asset('static/blocked_404.png') }}" alt="404" width="100%">
		<!-- <div class="i_logo"><img src="./my_logo.png" alt="logo" width="100%"></div> -->
	</div>

	<p class="tip" id="tipEle" style="font-size: 18px; color: #00b38a;">{{ $message }}</p>
</body>

<script>
	
</script>
</html>