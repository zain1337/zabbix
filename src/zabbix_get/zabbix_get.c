/* 
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/

#include "common.h"

#include "threads.h"
#include "comms.h"
#include "cfg.h"
#include "log.h"
#include "zbxgetopt.h"

char *progname = NULL;
char title_message[] = "ZABBIX get - Communicate with ZABBIX agent";
char usage_message[] = "[-hV] -s<host name or IP> [-p<port>] -k<key>";

#ifndef HAVE_GETOPT_LONG
char *help_message[] = {
        "Options:",
	"  -p <port number>         Specify port number of agent running on the host. Default is 10050.",
	"  -s <host name or IP>     Specify host name or IP address of a host.",
	"  -k <key of metric>       Specify metric name (key) we want to retrieve.",
	"  -h                       give this help",
	"  -V                       display version number",
	"",
	"Example: zabbix_get -s127.0.0.1 -p10050 -k\"system[procload]\"",
        0 /* end of text */
};
#else
char *help_message[] = {
        "Options:",
	"  -p --port <port number>        Specify port number of agent running on the host. Default is 10050.",
	"  -s --host <host name or IP>    Specify host name or IP address of a host.",
	"  -k --key <key of metric>       Specify metric name (key) we want to retrieve.",
	"  -h --help                      give this help",
	"  -V --version                   display version number",
	"",
	"Example: zabbix_get -s127.0.0.1 -p10050 -k\"system[procload]\"",
        0 /* end of text */
};
#endif

/* COMMAND LINE OPTIONS */

/* long options */
struct zbx_option longopts[] =
{
	{"port",	1,	0,	'p'},
	{"host",	1,	0,	's'},
	{"key",		1,	0,	'k'},
	{"help",	0,	0,	'h'},
	{"version",	0,	0,	'V'},
	{0,0,0,0}
};

/* short options */

static char     shortopts[] = "k:p:s:hV";

/* end of COMMAND LINE OPTIONS*/


#if !defined(_WINDOWS)

/******************************************************************************
 *                                                                            *
 * Function: signal_handler                                                   *
 *                                                                            *
 * Purpose: process signals                                                   *
 *                                                                            *
 * Parameters: sig - signal ID                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void    signal_handler( int sig )
{
	if( SIGALRM == sig )
	{
		signal( SIGALRM, signal_handler );
		zbx_error("Timeout while executing operation.");
	}
 
	if( SIGQUIT == sig || SIGINT == sig || SIGTERM == sig )
	{
/*		zbx_error("\nGot QUIT or INT or TERM signal. Exiting..." ); */
	}
	exit( FAIL );
}

#endif /* not WINDOWS */

/******************************************************************************
 *                                                                            *
 * Function: get_value                                                        *
 *                                                                            *
 * Purpose: connect to ZABBIX agent and receive value for given key           *
 *                                                                            *
 * Parameters: host   - serv name or IP address                               *
 *             port   - port number                                           *
 *             key    - item's key                                            *
 *             value_max_len - maximal size of value                          *
 *                                                                            *
 * Return value: SUCCEED - ok, FAIL - otherwise                               *
 *             value   - retrieved value                                      *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	get_value(
	const char	*host,
	unsigned short	port,
	const char	*key,
	char		**value
	)
{
	zbx_sock_t	s;
	int	ret;
	char	*buf,
		request[1024];

	assert(value);

	*value = NULL;

	if( SUCCEED == (ret = zbx_tcp_connect(&s, host, port)) )
	{
		zbx_snprintf(request, sizeof(request),"%s\n",key);
		if( SUCCEED == (ret = zbx_tcp_send(&s, request)) )
		{
			if( SUCCEED == (ret = zbx_tcp_recv_ext(&s, &buf, ZBX_TCP_READ_UNTIL_CLOSE)) )
			{
				zbx_rtrim(buf,"\r\n\0");
				*value = strdup(buf);
			}
		}
	}
	zbx_tcp_close(&s);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: main                                                             *
 *                                                                            *
 * Purpose: main function                                                     *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int main(int argc, char **argv)
{
	unsigned short	port	= 10050;
	int	ret	= SUCCEED;
	char	*value	= NULL;
	char	*host	= NULL;
	char	*key	= NULL;
	char	ch;

	progname = get_programm_name(argv[0]);

	/* Parse the command-line. */
	while ((ch = (char)zbx_getopt_long(argc, argv, shortopts, longopts, NULL)) != (char)EOF)
		switch (ch) {
			case 'k':
				key = strdup(zbx_optarg);
				break;
			case 'p':
				port = (unsigned short)atoi(zbx_optarg);
				break;
			case 's':
				host = strdup(zbx_optarg);
				break;
			case 'h':
				help();
				exit(-1);
				break;
			case 'V':
				version();
				exit(-1);
				break;
			default:
				usage();
				exit(-1);
				break;
		}

	if( (host==NULL) || (key==NULL))
	{
		usage();
		ret = FAIL;
	}

	if(ret == SUCCEED)
	{

#if !defined(_WINDOWS)
		signal( SIGINT,  signal_handler );
		signal( SIGTERM, signal_handler );
		signal( SIGQUIT, signal_handler );
		signal( SIGALRM, signal_handler );

		alarm(SENDER_TIMEOUT);
#endif /* not WINDOWS */

		ret = get_value(host, port, key, &value);

#if !defined(_WINDOWS)
		alarm(0);
#endif /* not WINDOWS */

		if(ret == SUCCEED)
		{
			printf("%s\n",value);
		}

		zbx_free(value);
	}

	zbx_free(host);
	zbx_free(key);

	return ret;
}
