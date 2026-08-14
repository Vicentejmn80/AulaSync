package com.example

import androidx.compose.foundation.layout.*
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.unit.dp
import androidx.navigation.NavHostController
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.currentBackStackEntryAsState
import androidx.navigation.compose.rememberNavController
import com.example.ui.theme.CyanAccent
import kotlinx.coroutines.launch

sealed class Screen(val route: String, val title: String, val icon: ImageVector) {
    object Login : Screen("login", "Login", Icons.Filled.Lock)
    object Dashboard : Screen("dashboard", "Inicio", Icons.Filled.Home)
    object Activities : Screen("activities", "Actividades", Icons.Filled.Assignment)
    object MyCourse : Screen("mycourse", "Mi Curso", Icons.Filled.Person)
    object Chat : Screen("chat", "Aulasync IA", Icons.Filled.AutoAwesome)
    object Calendar : Screen("calendar", "Calendario", Icons.Filled.DateRange)
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun MainApp(viewModel: NovaViewModel) {
    val navController = rememberNavController()
    val navBackStackEntry by navController.currentBackStackEntryAsState()
    val currentRoute = navBackStackEntry?.destination?.route
    
    val drawerState = rememberDrawerState(initialValue = DrawerValue.Closed)
    val scope = rememberCoroutineScope()
    
    val items = listOf(
        Screen.Dashboard,
        Screen.MyCourse,
        Screen.Calendar,
        Screen.Activities
    )

    if (currentRoute == Screen.Login.route) {
        LoginScreen(onLoginSuccess = {
            navController.navigate(Screen.Dashboard.route) {
                popUpTo(Screen.Login.route) { inclusive = true }
            }
        })
    } else {
        ModalNavigationDrawer(
            drawerState = drawerState,
            drawerContent = {
                ModalDrawerSheet(
                    drawerContainerColor = MaterialTheme.colorScheme.surface,
                    drawerContentColor = MaterialTheme.colorScheme.onSurface
                ) {
                    Spacer(Modifier.height(12.dp))
                    Text("AULASYNC", modifier = Modifier.padding(16.dp), style = MaterialTheme.typography.titleLarge, color = MaterialTheme.colorScheme.onSurface)
                    
                    val students by viewModel.students.collectAsState()
                    Row(modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp), verticalAlignment = androidx.compose.ui.Alignment.CenterVertically) {
                        Icon(Icons.Filled.Group, contentDescription = "Alumnos", tint = CyanAccent)
                        Spacer(modifier = Modifier.width(12.dp))
                        Text("Alumnos inscritos: ${students.size}", color = MaterialTheme.colorScheme.onSurfaceVariant)
                    }

                    Divider(color = MaterialTheme.colorScheme.surfaceVariant)
                    Spacer(Modifier.height(12.dp))
                    
                    val isDark by viewModel.themeIsDark.collectAsState()
                    Row(modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp), verticalAlignment = androidx.compose.ui.Alignment.CenterVertically) {
                        Text("Modo Oscuro", modifier = Modifier.weight(1f), color = MaterialTheme.colorScheme.onSurface)
                        Switch(
                            checked = isDark,
                            onCheckedChange = { viewModel.toggleTheme() },
                            colors = SwitchDefaults.colors(checkedThumbColor = CyanAccent)
                        )
                    }

                    items.forEach { item ->
                        NavigationDrawerItem(
                            icon = { Icon(item.icon, contentDescription = null) },
                            label = { Text(item.title) },
                            selected = currentRoute == item.route,
                            onClick = {
                                scope.launch { drawerState.close() }
                                navController.navigate(item.route) {
                                    popUpTo(navController.graph.startDestinationId)
                                    launchSingleTop = true
                                }
                            },
                            modifier = Modifier.padding(NavigationDrawerItemDefaults.ItemPadding)
                        )
                    }
                }
            }
        ) {
            Scaffold(
                topBar = {
                    TopAppBar(
                        title = { Text(items.find { it.route == currentRoute }?.title ?: "Aulasync") },
                        navigationIcon = {
                            IconButton(onClick = { scope.launch { drawerState.open() } }) {
                                Icon(Icons.Default.Menu, contentDescription = "Menu")
                            }
                        },
                        actions = {
                            IconButton(onClick = { /* TODO: Notificaciones */ }) {
                                Icon(Icons.Default.Notifications, contentDescription = "Notificaciones")
                            }
                        },
                        colors = TopAppBarDefaults.topAppBarColors(
                            containerColor = MaterialTheme.colorScheme.background,
                            titleContentColor = MaterialTheme.colorScheme.onBackground,
                            navigationIconContentColor = MaterialTheme.colorScheme.onBackground,
                            actionIconContentColor = MaterialTheme.colorScheme.onBackground
                        )
                    )
                },
                containerColor = MaterialTheme.colorScheme.background
            ) { innerPadding ->
                Box(modifier = Modifier.padding(innerPadding).fillMaxSize()) {
                    NavHost(navController = navController, startDestination = Screen.Login.route) {
                        composable(Screen.Login.route) { /* Handled outside Drawer */ }
                        composable(Screen.Dashboard.route) { DashboardScreen(viewModel) }
                        composable(Screen.MyCourse.route) { MyCourseScreen(viewModel) }
                        composable(Screen.Activities.route) { ActivitiesScreen(viewModel) }
                        composable(Screen.Chat.route) { ChatScreen(viewModel) }
                        composable(Screen.Calendar.route) { CalendarScreen(viewModel) }
                    }
                    
                    NovaSphere(navController = navController, viewModel = viewModel)
                }
            }
        }
    }
}
